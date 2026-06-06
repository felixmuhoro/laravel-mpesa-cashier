<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier\Exceptions;

class AlreadySubscribedException extends SubscriptionException
{
    public static function make(string $name): static
    {
        return new static("The subscriber already has an active subscription named [{$name}].");
    }
}
