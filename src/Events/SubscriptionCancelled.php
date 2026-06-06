<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier\Events;

use FelixMuhoro\MpesaCashier\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
    ) {}
}
