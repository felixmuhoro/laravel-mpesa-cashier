<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier;

use FelixMuhoro\MpesaCashier\Console\Commands\RenewSubscriptions;
use Illuminate\Support\ServiceProvider;

class MpesaCashierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mpesa-cashier.php',
            'mpesa-cashier',
        );

        // Bind the M-Pesa client interface to its default adapter.
        // Swap this binding in tests or if you use a different M-Pesa package.
        $this->app->singleton(MpesaClientInterface::class, MpesaClientAdapter::class);

        $this->app->singleton(SubscriptionManager::class, function ($app) {
            return new SubscriptionManager($app->make(MpesaClientInterface::class));
        });
    }

    public function boot(): void
    {
        // -----------------------------------------------------------------------
        // Publishing
        // -----------------------------------------------------------------------

        if ($this->app->runningInConsole()) {
            // Config
            $this->publishes([
                __DIR__ . '/../config/mpesa-cashier.php' => config_path('mpesa-cashier.php'),
            ], 'mpesa-cashier-config');

            // Migrations
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'mpesa-cashier-migrations');

            // Commands
            $this->commands([RenewSubscriptions::class]);
        }

        // -----------------------------------------------------------------------
        // Load migrations automatically (optional, respects config flag)
        // -----------------------------------------------------------------------
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // -----------------------------------------------------------------------
        // Routes
        // -----------------------------------------------------------------------
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        // -----------------------------------------------------------------------
        // Load plans defined in config
        // -----------------------------------------------------------------------
        $this->bootPlans();
    }

    private function bootPlans(): void
    {
        $plans = config('mpesa-cashier.plans', []);

        foreach ($plans as $id => $data) {
            if (! PlanRegistry::has($id)) {
                PlanRegistry::add(Plan::fromArray($id, $data));
            }
        }
    }
}
