<?php

namespace App\Console\Commands;

use App\Services\Payments\InvoiceDueNotifier;
use Illuminate\Console\Command;

/**
 * Scheduled daily in routes/console.php. Safe to run more than once a day.
 */
final class NotifyDueInvoicesCommand extends Command
{
    protected $signature = 'invoices:notify-due';

    protected $description = 'Send bell notifications for customer invoices that are due soon or overdue';

    public function handle(InvoiceDueNotifier $notifier): int
    {
        $sent = $notifier->run();

        $this->info("Reminders sent for {$sent} invoice(s).");

        return self::SUCCESS;
    }
}
