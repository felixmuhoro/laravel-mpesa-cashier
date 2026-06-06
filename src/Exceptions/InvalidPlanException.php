<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier\Exceptions;

class InvalidPlanException extends SubscriptionException
{
    public static function planNotFound(string $planId): static
    {
        return new static("Subscription plan [{$planId}] has not been defined.");
    }

    public static function invalidInterval(string $interval): static
    {
        return new static("Plan interval [{$interval}] is invalid. Use 'monthly' or 'yearly'.");
    }
}
