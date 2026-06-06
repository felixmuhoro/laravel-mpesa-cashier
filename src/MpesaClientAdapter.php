<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier;

/**
 * Default adapter that bridges to felixmuhoro/laravel-mpesa.
 *
 * The laravel-mpesa package exposes a `Mpesa` facade; we call it here so that
 * SubscriptionManager only depends on MpesaClientInterface (easy to mock/swap).
 */
class MpesaClientAdapter implements MpesaClientInterface
{
    public function stkPush(
        string $phone,
        int    $amount,
        string $reference,
        string $description,
        array  $callbackMeta = [],
    ): array {
        // felixmuhoro/laravel-mpesa Facade call.
        // The package is resolved from the container; we use the class name
        // directly to avoid a hard facade coupling.
        /** @var \FelixMuhoro\Mpesa\Mpesa $mpesa */
        $mpesa = app(\FelixMuhoro\Mpesa\Mpesa::class);

        return $mpesa->stkPush(
            phoneNumber:     $phone,
            amount:          $amount,
            accountReference: $reference,
            transactionDesc: $description,
        );
    }
}
