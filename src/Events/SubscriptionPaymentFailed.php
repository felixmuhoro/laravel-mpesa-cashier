<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier\Events;

use FelixMuhoro\MpesaCashier\Invoice;
use FelixMuhoro\MpesaCashier\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionPaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Invoice      $invoice,
        public readonly bool         $maxRetriesReached,
    ) {}
}
