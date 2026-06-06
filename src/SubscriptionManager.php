<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier;

use FelixMuhoro\MpesaCashier\Events\SubscriptionRenewed;
use FelixMuhoro\MpesaCashier\Events\SubscriptionPaymentFailed;
use FelixMuhoro\MpesaCashier\Events\SubscriptionCancelled;
use Illuminate\Support\Facades\Log;

/**
 * Core service that orchestrates billing cycles, STK pushes and renewals.
 */
class SubscriptionManager
{
    public function __construct(
        protected readonly MpesaClientInterface $mpesa,
    ) {}

    // -------------------------------------------------------------------------
    // Payment initiation
    // -------------------------------------------------------------------------

    /**
     * Fire an STK push for the given subscription.
     *
     * Returns the MerchantRequestID on success, or null on failure.
     */
    public function initiatePayment(Subscription $subscription, string $phone): ?string
    {
        $plan = $subscription->plan();

        $invoice = Invoice::create([
            'subscription_id' => $subscription->id,
            'user_id'         => $subscription->user_id,
            'invoice_number'  => Invoice::generateNumber(),
            'amount'          => $plan->amount,
            'currency'        => config('mpesa-cashier.currency', 'KES'),
            'status'          => 'pending',
            'phone'           => $phone,
            'due_date'        => now(),
        ]);

        try {
            $response = $this->mpesa->stkPush(
                phone:       $phone,
                amount:      $plan->amount,
                reference:   config('mpesa-cashier.account_reference', 'Subscription'),
                description: config('mpesa-cashier.transaction_description', 'Subscription Renewal'),
                callbackMeta: [
                    'subscription_id' => $subscription->id,
                    'invoice_id'      => $invoice->id,
                ],
            );

            Log::info('[MpesaCashier] STK push sent', [
                'subscription_id'   => $subscription->id,
                'merchant_request'  => $response['MerchantRequestID'] ?? null,
            ]);

            return $response['MerchantRequestID'] ?? null;
        } catch (\Throwable $e) {
            Log::error('[MpesaCashier] STK push failed', [
                'subscription_id' => $subscription->id,
                'error'           => $e->getMessage(),
            ]);

            $invoice->markFailed();
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Payment confirmation (called from the STK callback listener)
    // -------------------------------------------------------------------------

    /**
     * Handle a successful M-Pesa payment.
     *
     * @param  array{subscription_id:int, invoice_id:int, receipt:string} $payload
     */
    public function handlePaymentSuccess(array $payload): void
    {
        $subscription = Subscription::findOrFail($payload['subscription_id']);
        $invoice      = Invoice::findOrFail($payload['invoice_id']);

        $invoice->markPaid($payload['receipt']);

        // Advance billing date and mark active
        $subscription->advanceBillingDate();
        $subscription->markActive();

        event(new SubscriptionRenewed($subscription, $invoice));

        Log::info('[MpesaCashier] Subscription renewed', [
            'subscription_id' => $subscription->id,
            'plan_id'         => $subscription->plan_id,
            'receipt'         => $payload['receipt'],
        ]);
    }

    /**
     * Handle a failed/timeout M-Pesa payment.
     */
    public function handlePaymentFailure(array $payload): void
    {
        $subscription = Subscription::findOrFail($payload['subscription_id']);
        $invoice      = Invoice::findOrFail($payload['invoice_id']);

        $invoice->markFailed();
        $subscription->incrementRetry();

        $maxRetries = (int) config('mpesa-cashier.retry_attempts', 3);

        if ($subscription->retry_count >= $maxRetries) {
            $subscription->markPastDue();
            event(new SubscriptionPaymentFailed($subscription, $invoice, true));

            Log::warning('[MpesaCashier] Subscription past due after max retries', [
                'subscription_id' => $subscription->id,
            ]);
        } else {
            event(new SubscriptionPaymentFailed($subscription, $invoice, false));

            Log::info('[MpesaCashier] Subscription payment failed, will retry', [
                'subscription_id' => $subscription->id,
                'retry_count'     => $subscription->retry_count,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Renewal batch (called from RenewSubscriptions artisan command)
    // -------------------------------------------------------------------------

    /**
     * Process all subscriptions due for renewal today.
     * Returns the count of STK pushes initiated.
     */
    public function processRenewals(): int
    {
        $count = 0;

        $due = Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->whereDate('next_billing_date', '<=', today())
            ->get();

        foreach ($due as $subscription) {
            /** @var Subscription $subscription */
            $phone = $this->resolvePhone($subscription);

            if (! $phone) {
                Log::warning('[MpesaCashier] No phone for subscription renewal', [
                    'subscription_id' => $subscription->id,
                ]);
                continue;
            }

            $result = $this->initiatePayment($subscription, $phone);
            if ($result !== null) {
                $count++;
            }
        }

        return $count;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolvePhone(Subscription $subscription): ?string
    {
        $owner = $subscription->owner;

        // Convention: owner has a `mpesa_phone` or `phone` attribute.
        return $owner->mpesa_phone ?? $owner->phone ?? null;
    }
}
