<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier\Listeners;

use FelixMuhoro\MpesaCashier\SubscriptionManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listens to the M-Pesa STK callback (Payment Successful) event fired by
 * felixmuhoro/laravel-mpesa and delegates handling to SubscriptionManager.
 *
 * The payload is expected to contain:
 *   - subscription_id  (int)
 *   - invoice_id       (int)
 *   - receipt          (string) MpesaReceiptNumber
 *   - result_code      (int)    0 = success, otherwise failure
 */
class HandleSubscriptionPayment implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'subscriptions';

    public function __construct(
        protected readonly SubscriptionManager $manager,
    ) {}

    /**
     * Listens to: \FelixMuhoro\Mpesa\Events\StkPushCallbackReceived
     * (or a custom event your app fires after verifying the callback).
     */
    public function handle(object $event): void
    {
        $payload = $event->payload ?? $event->data ?? [];

        // Only handle callbacks that belong to a cashier subscription
        if (empty($payload['subscription_id']) || empty($payload['invoice_id'])) {
            return;
        }

        if (($payload['result_code'] ?? -1) === 0) {
            $this->manager->handlePaymentSuccess([
                'subscription_id' => (int) $payload['subscription_id'],
                'invoice_id'      => (int) $payload['invoice_id'],
                'receipt'         => $payload['receipt'] ?? $payload['mpesa_receipt'] ?? '',
            ]);
        } else {
            $this->manager->handlePaymentFailure([
                'subscription_id' => (int) $payload['subscription_id'],
                'invoice_id'      => (int) $payload['invoice_id'],
            ]);
        }
    }
}
