<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier\Console\Commands;

use FelixMuhoro\MpesaCashier\SubscriptionManager;
use Illuminate\Console\Command;

/**
 * Run this daily via cron:
 *   * * * * * php artisan mpesa-cashier:renew >> /dev/null 2>&1
 *
 * Or use Laravel's scheduler:
 *   $schedule->command('mpesa-cashier:renew')->daily();
 */
class RenewSubscriptions extends Command
{
    protected $signature   = 'mpesa-cashier:renew {--dry-run : List due subscriptions without initiating payments}';
    protected $description = 'Process subscription renewals due today via M-Pesa STK push.';

    public function handle(SubscriptionManager $manager): int
    {
        if ($this->option('dry-run')) {
            return $this->runDry();
        }

        $this->info('[MpesaCashier] Processing subscription renewals...');

        $count = $manager->processRenewals();

        $this->info("[MpesaCashier] Initiated {$count} STK push(es).");

        return self::SUCCESS;
    }

    private function runDry(): int
    {
        $due = \FelixMuhoro\MpesaCashier\Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->whereDate('next_billing_date', '<=', today())
            ->get(['id', 'user_id', 'name', 'plan_id', 'next_billing_date']);

        if ($due->isEmpty()) {
            $this->info('[MpesaCashier] No subscriptions due for renewal today.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'User ID', 'Name', 'Plan', 'Next Billing Date'],
            $due->map(fn ($s) => [
                $s->id,
                $s->user_id,
                $s->name,
                $s->plan_id,
                $s->next_billing_date,
            ]),
        );

        return self::SUCCESS;
    }
}
