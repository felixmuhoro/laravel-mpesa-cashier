<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier;

use FelixMuhoro\MpesaCashier\Exceptions\InvalidPlanException;

/**
 * In-process singleton registry that holds all defined Plan objects.
 */
final class PlanRegistry
{
    /** @var array<string, Plan> */
    private static array $plans = [];

    public static function add(Plan $plan): void
    {
        static::$plans[$plan->id] = $plan;
    }

    public static function get(string $id): Plan
    {
        if (! isset(static::$plans[$id])) {
            throw InvalidPlanException::planNotFound($id);
        }

        return static::$plans[$id];
    }

    public static function has(string $id): bool
    {
        return isset(static::$plans[$id]);
    }

    /** @return Plan[] */
    public static function all(): array
    {
        return array_values(static::$plans);
    }

    public static function flush(): void
    {
        static::$plans = [];
    }
}
