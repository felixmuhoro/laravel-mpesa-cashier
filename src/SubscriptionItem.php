<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line-item on a subscription (for multi-plan / add-on support).
 *
 * @property int    $id
 * @property int    $subscription_id
 * @property string $plan_id
 * @property int    $quantity
 */
class SubscriptionItem extends Model
{
    protected $table = 'subscription_items';

    protected $fillable = [
        'subscription_id',
        'plan_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): Plan
    {
        return PlanRegistry::get($this->plan_id);
    }
}
