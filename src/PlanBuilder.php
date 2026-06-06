<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier;

/**
 * Fluent builder for Plan objects.
 *
 * Usage:
 *   PlanBuilder::define('basic-monthly')
 *       ->name('Basic Monthly')
 *       ->amount(500)
 *       ->monthly()
 *       ->trialDays(7)
 *       ->graceDays(3)
 *       ->register();
 */
final class PlanBuilder
{
    public string $id;
    public string $name       = '';
    public int    $amount     = 0;
    public string $interval   = 'monthly';
    public int    $trialDays  = 0;
    public int    $graceDays  = 3;

    public function __construct(string $id)
    {
        $this->id   = $id;
        $this->name = $id;
    }

    // -------------------------------------------------------------------------
    // Static entry-point
    // -------------------------------------------------------------------------

    public static function define(string $id): self
    {
        return new self($id);
    }

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function amount(int $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function interval(string $interval): static
    {
        $this->interval = $interval;
        return $this;
    }

    public function monthly(): static
    {
        $this->interval = 'monthly';
        return $this;
    }

    public function yearly(): static
    {
        $this->interval = 'yearly';
        return $this;
    }

    public function trialDays(int $days): static
    {
        $this->trialDays = $days;
        return $this;
    }

    public function graceDays(int $days): static
    {
        $this->graceDays = $days;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Terminal methods
    // -------------------------------------------------------------------------

    /**
     * Build and register the plan in the PlanRegistry, then return the Plan.
     */
    public function register(): Plan
    {
        $plan = Plan::fromBuilder($this);
        PlanRegistry::add($plan);
        return $plan;
    }

    /**
     * Build without registering (useful in tests or one-off plans).
     */
    public function build(): Plan
    {
        return Plan::fromBuilder($this);
    }
}
