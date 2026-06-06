<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $id
 * @property int         $user_id
 * @property string      $name
 * @property string      $plan_id
 * @property string      $status           active|cancelled|past_due|trialing
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $grace_period_ends_at
 * @property Carbon      $next_billing_date
 * @property int         $retry_count
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 */
class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id',
        'name',
        'plan_id',
        'status',
        'trial_ends_at',
        'ends_at',
        'grace_period_ends_at',
        'next_billing_date',
        'retry_count',
    ];

    protected $casts = [
        'trial_ends_at'        => 'datetime',
        'ends_at'              => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'next_billing_date'    => 'datetime',
        'retry_count'          => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function owner(): BelongsTo
    {
        $model = config('mpesa-cashier.model', \App\Models\User::class);
        return $this->belongsTo($model, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // -------------------------------------------------------------------------
    // Status checks
    // -------------------------------------------------------------------------

    public function active(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true)
            || $this->onGracePeriod();
    }

    public function trialing(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at?->isFuture();
    }

    public function onTrial(): bool
    {
        return $this->trialing();
    }

    public function cancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function pastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function onGracePeriod(): bool
    {
        return $this->grace_period_ends_at !== null
            && $this->grace_period_ends_at->isFuture();
    }

    public function ended(): bool
    {
        return $this->cancelled() && ! $this->onGracePeriod();
    }

    // -------------------------------------------------------------------------
    // Plan
    // -------------------------------------------------------------------------

    public function plan(): Plan
    {
        return PlanRegistry::get($this->plan_id);
    }

    // -------------------------------------------------------------------------
    // Mutations
    // -------------------------------------------------------------------------

    /**
     * Cancel at the end of the current billing period (sets grace period).
     */
    public function cancel(): static
    {
        $graceDays = $this->plan()->graceDays;

        $this->status                = 'cancelled';
        $this->ends_at               = $this->next_billing_date ?? now();
        $this->grace_period_ends_at  = $this->ends_at->copy()->addDays($graceDays);
        $this->save();

        return $this;
    }

    /**
     * Cancel immediately — no grace period.
     */
    public function cancelNow(): static
    {
        $this->status                = 'cancelled';
        $this->ends_at               = now();
        $this->grace_period_ends_at  = null;
        $this->save();

        return $this;
    }

    /**
     * Resume a cancelled subscription that is still in the grace period.
     */
    public function resume(): static
    {
        if (! $this->onGracePeriod()) {
            throw new \LogicException('Cannot resume a subscription that is not on a grace period.');
        }

        $this->status               = 'active';
        $this->ends_at              = null;
        $this->grace_period_ends_at = null;
        $this->save();

        return $this;
    }

    /**
     * Advance the billing date by one billing cycle.
     */
    public function advanceBillingDate(): static
    {
        $plan = $this->plan();

        $this->next_billing_date = match ($plan->interval) {
            'monthly' => $this->next_billing_date->copy()->addMonth(),
            'yearly'  => $this->next_billing_date->copy()->addYear(),
        };

        $this->save();

        return $this;
    }

    /**
     * Mark the subscription as active and clear any past-due/retry state.
     */
    public function markActive(): static
    {
        $this->status      = 'active';
        $this->retry_count = 0;
        $this->save();

        return $this;
    }

    public function markPastDue(): static
    {
        $this->status = 'past_due';
        $this->save();

        return $this;
    }

    public function incrementRetry(): static
    {
        $this->increment('retry_count');
        return $this;
    }
}
