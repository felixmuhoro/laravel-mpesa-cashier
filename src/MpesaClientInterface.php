<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier;

/**
 * Contract that the SubscriptionManager uses to interact with M-Pesa.
 * The default implementation delegates to felixmuhoro/laravel-mpesa.
 */
interface MpesaClientInterface
{
    /**
     * Initiate an STK push (Lipa Na M-Pesa Online).
     *
     * @param  string $phone        Safaricom number in international format (2547XXXXXXXX)
     * @param  int    $amount       Amount in KES (whole numbers only)
     * @param  string $reference    Account reference shown on the subscriber's phone
     * @param  string $description  Transaction description
     * @param  array  $callbackMeta Extra data to persist and retrieve on callback
     * @return array  Raw API response from Safaricom
     */
    public function stkPush(
        string $phone,
        int    $amount,
        string $reference,
        string $description,
        array  $callbackMeta = [],
    ): array;
}
