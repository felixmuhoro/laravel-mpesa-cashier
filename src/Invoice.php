<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $subscription_id
 * @property int         $user_id
 * @property string      $invoice_number
 * @property int         $amount
 * @property string      $currency
 * @property string      $status          pending|paid|failed
 * @property string|null $mpesa_receipt
 * @property string|null $phone
 * @property Carbon      $due_date
 * @property Carbon|null $paid_at
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 */
class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'subscription_id',
        'user_id',
        'invoice_number',
        'amount',
        'currency',
        'status',
        'mpesa_receipt',
        'phone',
        'due_date',
        'paid_at',
    ];

    protected $casts = [
        'amount'   => 'integer',
        'due_date' => 'datetime',
        'paid_at'  => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function owner(): BelongsTo
    {
        $model = config('mpesa-cashier.model', \App\Models\User::class);
        return $this->belongsTo($model, 'user_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function formattedAmount(): string
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }

    /**
     * Generate a human-readable invoice number.
     * Format: INV-YYYYMMDD-{id padded to 6 digits}
     */
    public static function generateNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ymd') . '-';
        $last   = static::whereDate('created_at', today())->max('id') ?? 0;
        return $prefix . str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
    }

    public function markPaid(string $mpesaReceipt): static
    {
        $this->status        = 'paid';
        $this->mpesa_receipt = $mpesaReceipt;
        $this->paid_at       = now();
        $this->save();

        return $this;
    }

    public function markFailed(): static
    {
        $this->status = 'failed';
        $this->save();

        return $this;
    }
}
