<?php

namespace App\Http\Controllers;

use App\Enums\PartUnit;
use App\Models\User;
use App\Notifications\InvoiceDueNotification;
use App\Notifications\PurchaseDueNotification;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The user's own in-app notifications (bell icon). Always read through the user's
 * notifications() relation, so nobody can see or mark someone else's.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // "items", not "notifications": that name is the shared unread-count prop.
        return Inertia::render('Notifications/Index', [
            'items' => $user->notifications()
                ->latest()
                ->paginate(20)
                ->through(fn (DatabaseNotification $notification) => $this->present($notification)),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $found = $user->notifications()->findOrFail($notification);
        $found->markAsRead();

        return redirect($this->present($found)['url']);
    }

    public function readAll(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /**
     * Text is built here, in the reader's language, from the raw values stored.
     *
     * @return array{id: string, title: string, body: string, url: string, read: bool, created_at_label: string}
     */
    private function present(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        [$title, $body, $url] = match ($data['kind'] ?? 'low_stock') {
            PurchaseDueNotification::DUE_SOON, PurchaseDueNotification::OVERDUE => $this->purchaseDue($data),
            InvoiceDueNotification::DUE_SOON, InvoiceDueNotification::OVERDUE => $this->invoiceDue($data),
            default => $this->lowStock($data),
        };

        return [
            'id' => $notification->id,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'read' => $notification->read_at !== null,
            'created_at_label' => $notification->created_at?->diffForHumans() ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{string, string, string}
     */
    private function lowStock(array $data): array
    {
        $unit = PartUnit::tryFrom((string) ($data['unit'] ?? ''))?->label() ?? '';

        return [
            __('notifications.low_stock.title', ['part' => (string) ($data['part_name'] ?? '')]),
            __('notifications.low_stock.body', [
                'qty' => Quantity::format(Quantity::fromDecimal((string) ($data['qty'] ?? '0'))),
                'threshold' => Quantity::format(Quantity::fromDecimal((string) ($data['threshold'] ?? '0'))),
                'unit' => $unit,
                'branch' => (string) ($data['branch_name'] ?? ''),
            ]),
            route('parts.show', (int) ($data['part_id'] ?? 0)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{string, string, string}
     */
    private function invoiceDue(array $data): array
    {
        $key = $data['kind'] === InvoiceDueNotification::OVERDUE ? 'overdue' : 'due_soon';
        $due = isset($data['due_date']) ? Carbon::parse((string) $data['due_date'])->translatedFormat('j M Y') : '';
        $customer = isset($data['customer_name']) ? (string) $data['customer_name'] : __('invoices.walk_in_customer');

        return [
            __("notifications.invoice_{$key}.title", ['customer' => $customer]),
            __("notifications.invoice_{$key}.body", [
                'number' => (string) ($data['number'] ?? ''),
                'amount' => Money::format((int) ($data['outstanding'] ?? 0)),
                'date' => $due,
                'branch' => (string) ($data['branch_name'] ?? ''),
            ]),
            route('invoices.show', (int) ($data['invoice_id'] ?? 0)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{string, string, string}
     */
    private function purchaseDue(array $data): array
    {
        $key = $data['kind'] === PurchaseDueNotification::OVERDUE ? 'overdue' : 'due_soon';
        $due = isset($data['due_date']) ? Carbon::parse((string) $data['due_date'])->translatedFormat('j M Y') : '';

        return [
            __("notifications.purchase_{$key}.title", ['supplier' => (string) ($data['supplier_name'] ?? '')]),
            __("notifications.purchase_{$key}.body", [
                'number' => (string) ($data['number'] ?? ''),
                'amount' => Money::format((int) ($data['outstanding'] ?? 0)),
                'date' => $due,
                'branch' => (string) ($data['branch_name'] ?? ''),
            ]),
            route('purchases.show', (int) ($data['purchase_id'] ?? 0)),
        ];
    }
}
