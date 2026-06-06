<?php

return [

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Cashier Model
    |--------------------------------------------------------------------------
    |
    | The fully-qualified model class that uses the Billable trait. Cashier
    | uses this to resolve the subscriber when a payment callback arrives.
    |
    */
    'model' => env('MPESA_CASHIER_MODEL', \App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | M-Pesa Kenya only supports KES. Adjust for other regions.
    |
    */
    'currency' => env('MPESA_CASHIER_CURRENCY', 'KES'),

    /*
    |--------------------------------------------------------------------------
    | Default Grace Period (days)
    |--------------------------------------------------------------------------
    |
    | How many days after a failed renewal payment the subscription remains
    | active before it is marked as past_due / cancelled.
    |
    */
    'grace_days' => env('MPESA_CASHIER_GRACE_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Default Trial Period (days)
    |--------------------------------------------------------------------------
    */
    'trial_days' => env('MPESA_CASHIER_TRIAL_DAYS', 0),

    /*
    |--------------------------------------------------------------------------
    | STK Push Account Reference
    |--------------------------------------------------------------------------
    |
    | Shown on the subscriber's M-Pesa menu. Keep it short (≤12 chars).
    |
    */
    'account_reference' => env('MPESA_CASHIER_ACCOUNT_REF', 'Subscription'),

    /*
    |--------------------------------------------------------------------------
    | STK Push Transaction Description
    |--------------------------------------------------------------------------
    */
    'transaction_description' => env('MPESA_CASHIER_TX_DESC', 'Subscription Renewal'),

    /*
    |--------------------------------------------------------------------------
    | Renewal Retry Attempts
    |--------------------------------------------------------------------------
    |
    | How many times the system retries an STK push on the renewal date before
    | giving the subscription a past_due status.
    |
    */
    'retry_attempts' => env('MPESA_CASHIER_RETRY_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Plans Registry
    |--------------------------------------------------------------------------
    |
    | Plans defined here are loaded at boot. You may also define plans
    | programmatically via PlanBuilder::define() in a service provider.
    |
    | Example:
    |   'plans' => [
    |       'basic-monthly' => [
    |           'name'        => 'Basic Monthly',
    |           'amount'      => 500,
    |           'interval'    => 'monthly',
    |           'trial_days'  => 7,
    |           'grace_days'  => 3,
    |       ],
    |   ],
    |
    */
    'plans' => [],

    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    */
    'invoice' => [
        'company_name'    => env('MPESA_CASHIER_COMPANY', config('app.name', 'My App')),
        'company_address' => env('MPESA_CASHIER_ADDRESS', ''),
        'logo'            => env('MPESA_CASHIER_LOGO', ''),
    ],

];
