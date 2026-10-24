<?php

namespace App\Http\Controllers;

use App\Enums\PartUnit;
use App\Models\User;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
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
        $unit = PartUnit::tryFrom((string) ($data['unit'] ?? ''))?->label() ?? '';

        return [
            'id' => $notification->id,
            'title' => __('notifications.low_stock.title', ['part' => (string) ($data['part_name'] ?? '')]),
            'body' => __('notifications.low_stock.body', [
                'qty' => Quantity::format(Quantity::fromDecimal((string) ($data['qty'] ?? '0'))),
                'threshold' => Quantity::format(Quantity::fromDecimal((string) ($data['threshold'] ?? '0'))),
                'unit' => $unit,
                'branch' => (string) ($data['branch_name'] ?? ''),
            ]),
            'url' => route('parts.show', (int) ($data['part_id'] ?? 0)),
            'read' => $notification->read_at !== null,
            'created_at_label' => $notification->created_at?->diffForHumans() ?? '',
        ];
    }
}
