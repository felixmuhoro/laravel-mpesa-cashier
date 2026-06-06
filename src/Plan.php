<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier;

use FelixMuhoro\MpesaCashier\Exceptions\InvalidPlanException;

/**
 * Immutable value-object that describes a subscription plan.
 */
final class Plan
{
    public readonly string $id;
    public readonly string $name;
    public readonly int $amount;          // in smallest currency unit (KES cents not used; whole KES)
    public readonly string $interval;    // 'monthly' | 'yearly'
    public readonly int $trialDays;
    public readonly int $graceDays;

    private function __construct(
        string $id,
        string $name,
        int $amount,
        string $interval,
        int $trialDays,
        int $graceDays,
    ) {
        $this->id         = $id;
        $this->name       = $name;
        $this->amount     = $amount;
        $this->interval   = $interval;
        $this->trialDays  = $trialDays;
        $this->graceDays  = $graceDays;
    }

    // -------------------------------------------------------------------------
    // Fluent builder factory
    // -------------------------------------------------------------------------

    public static function create(string $id): PlanBuilder
    {
        return new PlanBuilder($id);
    }

    /** @internal Called by PlanBuilder once all attributes are set. */
    public static function fromBuilder(PlanBuilder $builder): self
    {
        if (! in_array($builder->interval, ['monthly', 'yearly'], true)) {
            throw InvalidPlanException::invalidInterval($builder->interval);
        }

        return new self(
            id:         $builder->id,
            name:       $builder->name,
            amount:     $builder->amount,
            interval:   $builder->interval,
            trialDays:  $builder->trialDays,
            graceDays:  $builder->graceDays,
        );
    }

    /** @internal Hydrate from a config array. */
    public static function fromArray(string $id, array $data): self
    {
        return new self(
            id:        $id,
            name:      $data['name']        ?? $id,
            amount:    (int) ($data['amount']     ?? 0),
            interval:  $data['interval']    ?? 'monthly',
            trialDays: (int) ($data['trial_days'] ?? 0),
            graceDays: (int) ($data['grace_days'] ?? config('mpesa-cashier.grace_days', 3)),
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isMonthly(): bool
    {
        return $this->interval === 'monthly';
    }

    public function isYearly(): bool
    {
        return $this->interval === 'yearly';
    }

    public function hasTrial(): bool
    {
        return $this->trialDays > 0;
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'amount'      => $this->amount,
            'interval'    => $this->interval,
            'trial_days'  => $this->trialDays,
            'grace_days'  => $this->graceDays,
        ];
    }
}
