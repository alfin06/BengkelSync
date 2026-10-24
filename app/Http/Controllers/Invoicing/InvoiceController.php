<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\Permission;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\StoreInvoiceRequest;
use App\Http\Requests\Invoicing\VoidInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentReceipt;
use App\Models\ServiceItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use App\Models\WorkOrderServiceLine;
use App\Services\Invoicing\InvoiceLock;
use App\Services\Invoicing\InvoiceService;
use App\Support\BranchTime;
use App\Support\Money;
use App\Support\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(InvoiceService::FILTERS)],
        ]);
        $status = $filters['status'] ?? 'all';

        return Inertia::render('Invoices/Index', [
            'invoices' => $this->invoices
                ->search($filters['search'] ?? null, $status)
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Invoice $invoice) => InvoiceResource::make($invoice)->resolve($request)),
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $status],
            'can' => ['create' => $request->user()?->can('create', Invoice::class) ?? false],
        ]);
    }

    /**
     * From a finished work order (?work_order=ID) or a walk-in sale.
     */
    public function create(Request $request, TenantContext $context): Response|RedirectResponse
    {
        Gate::authorize('create', Invoice::class);

        /** @var User $user */
        $user = $request->user();
        $branch = $context->requireBranch();
        $workOrder = null;

        if ($request->filled('work_order')) {
            $workOrder = WorkOrder::query()->with(['vehicle', 'customer', 'services', 'parts.part'])->findOrFail($request->integer('work_order'));

            if ($workOrder->status !== WorkOrderStatus::Completed) {
                return redirect()->route('work-orders.show', $workOrder)->with('error', __('invoices.work_order_not_completed'));
            }
        }

        return Inertia::render('Invoices/Create', [
            'workOrder' => $workOrder === null ? null : $this->workOrderForInvoice($workOrder),
            'catalog' => $workOrder === null
                ? ServiceItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'default_price'])
                : [],
            'tax' => [
                'enabled' => $branch->tax_enabled,
                'name' => $branch->tax_name,
                'rate' => $branch->tax_rate,
                'inclusive' => $branch->tax_inclusive,
            ],
            'today' => $context->today(),
            'can' => [
                'discount' => $user->can('discount', Invoice::class),
                'toggleTax' => $user->can('toggleTax', Invoice::class),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $invoice = $request->input('mode') === 'work_order'
            ? $this->invoices->createFromWorkOrder($request->workOrder() ?? abort(404), $request->invoiceHeader(), $request->workOrderPrices())
            : $this->invoices->createWalkIn($request->customer(), $request->invoiceHeader(), $request->walkInLines());

        return redirect()->route('invoices.show', $invoice)->with('success', __('invoices.created', ['number' => $invoice->number]));
    }

    public function show(Request $request, Invoice $invoice, TenantContext $context, InvoiceLock $lock): Response
    {
        Gate::authorize('view', $invoice);

        /** @var User $user */
        $user = $request->user();
        $invoice->load(['customer', 'workOrder.vehicle', 'lines.part', 'receipts.payments.method', 'receipts.payments.voider', 'receipts.receiver']);
        $canPay = $user->can('pay', $invoice);
        $today = $context->today();

        return Inertia::render('Invoices/Show', [
            'invoice' => InvoiceResource::make($invoice)->resolve($request),
            'receipts' => $invoice->receipts->map(fn (PaymentReceipt $receipt) => [
                'id' => $receipt->id,
                'number' => $receipt->number,
                'paid_on' => $receipt->paid_on->toDateString(),
                'amount_label' => Money::format($receipt->activeAmount()),
                'received_by' => $receipt->receiver?->name,
                'fully_voided' => $receipt->isFullyVoided(),
                'lines' => $receipt->payments->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'method' => $payment->method->name,
                    'amount_label' => Money::format($payment->amount),
                    'surcharge_label' => $payment->surcharge_amount > 0 ? Money::format($payment->surcharge_amount) : null,
                    'reference' => $payment->reference,
                    'voided' => $payment->isVoided(),
                    'void_reason' => $payment->void_reason,
                    'voided_by' => $payment->voider?->name,
                    'voided_at_label' => BranchTime::label($payment->voided_at),
                ])->all(),
            ])->all(),
            // The "Bayar" dialog: the branch's active methods, today, and a due date to
            // suggest when part stays unpaid (the invoice's own, else in 7 days).
            'methods' => $canPay
                ? PaymentMethod::query()->where('is_active', true)->orderBy('sort')->orderBy('name')->get()->map(fn (PaymentMethod $method) => [
                    'id' => $method->id,
                    'name' => $method->name,
                    'type' => $method->type->value,
                    'surcharge_percent' => $method->surcharge_percent,
                    'needs_reference' => $method->needs_reference,
                ])->all()
                : [],
            'today' => $today,
            'locked' => $lock->isLocked($invoice),
            'suggestedDueDate' => $invoice->due_date?->toDateString() ?? Carbon::parse($today)->addDays(7)->toDateString(),
            'can' => [
                'void' => $user->can('void', $invoice),
                'update' => $user->can('update', $invoice),
                'pay' => $canPay,
                'voidPayment' => $user->can(Permission::PaymentDelete->value) && $lock->allows($user, $invoice),
            ],
        ]);
    }

    /**
     * A5, plain black text: prints well on 2-ply paper (dot-matrix or laser).
     */
    public function print(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['customer', 'workOrder.vehicle', 'lines.part', 'branch']);

        return Inertia::render('Invoices/Print', [
            'invoice' => InvoiceResource::make($invoice)->resolve($request),
            'branch' => [
                'name' => $invoice->branch->name,
                'address' => $invoice->branch->address,
                'phone' => $invoice->branch->phone,
                'npwp' => $invoice->branch->npwp,
                'footer' => $invoice->branch->invoice_footer,
                'logo_url' => $invoice->branch->logoUrl(),
            ],
        ]);
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->invoices->void($invoice, $request->string('reason')->toString());

        return back()->with('success', __('invoices.voided', ['number' => $invoice->number]));
    }

    /**
     * @return array<string, mixed>
     */
    private function workOrderForInvoice(WorkOrder $workOrder): array
    {
        return [
            'id' => $workOrder->id,
            'number' => $workOrder->number,
            'customer_name' => $workOrder->customer->name,
            'vehicle' => $workOrder->vehicle->plate_number.' · '.$workOrder->vehicle->displayName(),
            'lines' => [
                ...$workOrder->services->map(fn (WorkOrderServiceLine $line) => [
                    'source' => 'service',
                    'id' => $line->id,
                    'description' => $line->description,
                    'qty' => (string) $line->qty,
                    'qty_label' => (string) $line->qty,
                    'unit_label' => null,
                    'unit_price' => $line->unit_price,
                ])->all(),
                ...$workOrder->parts->map(fn (WorkOrderPart $line) => [
                    'source' => 'part',
                    'id' => $line->id,
                    'description' => $line->part->name,
                    'qty' => rtrim(rtrim($line->qty, '0'), '.'),
                    'qty_label' => Quantity::format($line->qtyHundredths()),
                    'unit_label' => $line->part->unit->label(),
                    'unit_price' => $line->unit_price,
                ])->all(),
            ],
        ];
    }
}
