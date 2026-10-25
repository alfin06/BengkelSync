<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Tenant;
use App\Services\Invoicing\InvoiceShareService;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The invoice a customer opens from the WhatsApp link. The route is signed and expires
 * (InvoiceShareService::link()), so ids in it cannot be changed or guessed; it needs no
 * login and shows only that invoice, read-only. The tenant and branch from the signed URL
 * are put in the TenantContext, so the usual scopes apply.
 */
class PublicInvoiceController extends Controller
{
    public function __invoke(Request $request, int $tenant, int $branch, int $invoice, TenantContext $context, InvoiceShareService $share): Response
    {
        $shop = Tenant::query()->find($tenant);
        abort_if($shop === null || ! $shop->isActive(), 404);

        $place = $context->run($shop, null, fn () => Branch::query()->withTrashed()->find($branch));
        abort_if($place === null, 404);

        $response = $context->run($shop, $place, function () use ($request, $invoice, $share, $place) {
            $found = Invoice::query()
                ->with(['customer', 'workOrder.vehicle', 'lines.part', 'receipts.payments.method'])
                ->find($invoice);
            abort_if($found === null, 404);

            return Inertia::render('Invoices/Public', [
                'invoice' => InvoiceResource::make($found)->resolve($request),
                'branch' => $share->branchDetails($place, embedLogo: true),
                'payments' => $found->receipts->flatMap(fn (PaymentReceipt $receipt) => $receipt->payments
                    ->reject(fn (Payment $payment) => $payment->isVoided())
                    ->map(fn (Payment $payment) => [
                        'id' => $payment->id,
                        'paid_on' => $receipt->paid_on->toDateString(),
                        'method' => $payment->method->name,
                        'amount_label' => Money::format($payment->amount),
                    ]))->values()->all(),
            ])->toResponse($request);
        });

        // Keep the link out of search engines and shared caches. (The app-wide
        // Referrer-Policy already never sends the path or signature to other sites.)
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
