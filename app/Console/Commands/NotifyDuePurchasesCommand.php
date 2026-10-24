<?php

namespace App\Console\Commands;

use App\Services\Purchasing\PurchaseDueNotifier;
use Illuminate\Console\Command;

/**
 * Scheduled daily in routes/console.php. Safe to run more than once a day.
 */
final class NotifyDuePurchasesCommand extends Command
{
    protected $signature = 'purchases:notify-due';

    protected $description = 'Send bell notifications for supplier bills that are due soon or overdue';

    public function handle(PurchaseDueNotifier $notifier): int
    {
        $sent = $notifier->run();

        $this->info("Reminders sent for {$sent} purchase(s).");

        return self::SUCCESS;
    }
}
