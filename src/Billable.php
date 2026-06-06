<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Billable trait — attach to any Eloquent model that needs M-Pesa subscriptions.
 *
 * Usage:
 *   class User extends Model
 *   {
 *       use \FelixMuhoro\MpesaCashier\Billable;
 *   }
 */
trait Billable
{
    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id')
                    ->orderByDesc('created_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'user_id')
                    ->orderByDesc('created_at');
    }

    // -------------------------------------------------------------------------
    // Subscription builder
    // -------------------------------------------------------------------------

    /**
     * Begin building a new subscription.
     *
     *   $user->newSubscription('default', 'basic-monthly')
     *        ->withTrial(7)
     *        ->create('2547XXXXXXXX');
     */
    public function newSubscription(string $name, string $planId): SubscriptionBuilder
    {
        return new SubscriptionBuilder($this, $name, $planId);
    }

    /**
     * Shorthand: subscribe to a plan immediately with optional phone override.
     * Uses the model's `mpesa_phone` or `phone` attribute if $phone is omitted.
     */
    public function subscribe(string $planId, ?string $phone = null, string $name = 'default'): Subscription
    {
        $resolvedPhone = $phone ?? $this->mpesa_phone ?? $this->phone ?? '';

        return $this->newSubscription($name, $planId)->create($resolvedPhone);
    }

    // -------------------------------------------------------------------------
    // Subscription accessors
    // -------------------------------------------------------------------------

    /**
     * Retrieve a named subscription (defaults to 'default').
     */
    public function subscription(string $name = 'default'): ?Subscription
    {
        return $this->subscriptions()->where('name', $name)->first();
    }

    /**
     * Determine if the user is subscribed to the given plan on the given subscription.
     */
    public function subscribed(string $name = 'default', ?string $planId = null): bool
    {
        $subscription = $this->subscription($name);

        if (! $subscription || ! $subscription->active()) {
            return false;
        }

        return $planId === null || $subscription->plan_id === $planId;
    }

    /**
     * Determine if the user is on a trial.
     */
    public function onTrial(string $name = 'default'): bool
    {
        return $this->subscription($name)?->onTrial() ?? false;
    }

    /**
     * Determine if the subscription is on a grace period after cancellation.
     */
    public function onGracePeriod(string $name = 'default'): bool
    {
        return $this->subscription($name)?->onGracePeriod() ?? false;
    }

    // -------------------------------------------------------------------------
    // Cancellation & resumption
    // -------------------------------------------------------------------------

    /**
     * Cancel the named subscription at end of period.
     */
    public function cancelSubscription(string $name = 'default'): Subscription
    {
        $sub = $this->subscription($name);

        if (! $sub) {
            throw new \LogicException("No subscription named [{$name}] found.");
        }

        return $sub->cancel();
    }

    /**
     * Cancel the named subscription immediately.
     */
    public function cancelNow(string $name = 'default'): Subscription
    {
        $sub = $this->subscription($name);

        if (! $sub) {
            throw new \LogicException("No subscription named [{$name}] found.");
        }

        return $sub->cancelNow();
    }

    /**
     * Resume a cancelled subscription that is within the grace period.
     */
    public function resume(string $name = 'default'): Subscription
    {
        $sub = $this->subscription($name);

        if (! $sub) {
            throw new \LogicException("No subscription named [{$name}] found.");
        }

        return $sub->resume();
    }
}
