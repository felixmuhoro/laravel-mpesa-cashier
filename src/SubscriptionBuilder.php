<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier;

use FelixMuhoro\MpesaCashier\Exceptions\AlreadySubscribedException;
use Illuminate\Database\Eloquent\Model;

/**
 * Fluent builder returned by Billable::newSubscription().
 *
 * Usage:
 *   $user->newSubscription('default', 'basic-monthly')
 *        ->withTrial(7)
 *        ->withGrace(3)
 *        ->create($phone);
 */
class SubscriptionBuilder
{
    protected ?int $trialDays  = null;
    protected ?int $graceDays  = null;
    protected bool $skipTrial  = false;

    public function __construct(
        protected readonly Model  $owner,
        protected readonly string $name,
        protected readonly string $planId,
    ) {}

    // -------------------------------------------------------------------------
    // Fluent options
    // -------------------------------------------------------------------------

    public function withTrial(int $days): static
    {
        $this->trialDays = $days;
        return $this;
    }

    public function skipTrial(): static
    {
        $this->skipTrial = true;
        return $this;
    }

    public function withGrace(int $days): static
    {
        $this->graceDays = $days;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Terminal: create
    // -------------------------------------------------------------------------

    /**
     * Persist the subscription and (if not trialing) fire the first STK push.
     *
     * @param  string $phone  Subscriber's Safaricom number (e.g. 2547XXXXXXXX)
     */
    public function create(string $phone): Subscription
    {
        if ($this->owner->subscribed($this->name)) {
            throw AlreadySubscribedException::make($this->name);
        }

        $plan = PlanRegistry::get($this->planId);

        $trialDays = $this->skipTrial ? 0 : ($this->trialDays ?? $plan->trialDays);
        $graceDays = $this->graceDays ?? $plan->graceDays;

        $now         = now();
        $trialEndsAt = $trialDays > 0 ? $now->copy()->addDays($trialDays) : null;

        // First billing date: after trial (if any), otherwise now.
        $firstBillingDate = $trialEndsAt?->copy() ?? $now->copy();

        $firstBillingDate = match ($plan->interval) {
            'monthly' => $firstBillingDate->addMonth(),
            'yearly'  => $firstBillingDate->addYear(),
        };

        $status = $trialEndsAt ? 'trialing' : 'active';

        /** @var Subscription $subscription */
        $subscription = Subscription::create([
            'user_id'           => $this->owner->getKey(),
            'name'              => $this->name,
            'plan_id'           => $this->planId,
            'status'            => $status,
            'trial_ends_at'     => $trialEndsAt,
            'ends_at'           => null,
            'grace_period_ends_at' => null,
            'next_billing_date' => $firstBillingDate,
            'retry_count'       => 0,
        ]);

        // Create matching subscription item
        SubscriptionItem::create([
            'subscription_id' => $subscription->id,
            'plan_id'         => $this->planId,
            'quantity'        => 1,
        ]);

        // If no trial, initiate first payment immediately
        if (! $trialEndsAt) {
            /** @var SubscriptionManager $manager */
            $manager = app(SubscriptionManager::class);
            $manager->initiatePayment($subscription, $phone);
        }

        return $subscription;
    }
}
