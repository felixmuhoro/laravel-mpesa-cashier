<?php

use FelixMuhoro\MpesaCashier\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('mpesa-cashier')
    ->name('mpesa-cashier.')
    ->group(function () {

        // Plans listing (public within auth middleware)
        Route::get('plans', [SubscriptionController::class, 'plans'])->name('plans');

        // Subscribe
        Route::post('subscribe', [SubscriptionController::class, 'subscribe'])->name('subscribe');

        // Per-subscription actions
        Route::prefix('subscriptions/{subscription}')->name('subscriptions.')->group(function () {
            Route::post('cancel',   [SubscriptionController::class, 'cancel'])->name('cancel');
            Route::post('resume',   [SubscriptionController::class, 'resume'])->name('resume');
            Route::get('invoices',  [SubscriptionController::class, 'subscriptionInvoices'])->name('invoices');
        });

        // All invoices for authenticated user
        Route::get('invoices', [SubscriptionController::class, 'invoices'])->name('invoices');
    });
