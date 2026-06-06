<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier\Tests;

use FelixMuhoro\MpesaCashier\MpesaCashierServiceProvider;
use FelixMuhoro\MpesaCashier\MpesaClientInterface;
use FelixMuhoro\MpesaCashier\PlanRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlanRegistry::flush();

        // Bind a mock M-Pesa client so tests do not hit the real API
        $this->app->singleton(MpesaClientInterface::class, function () {
            return new class implements MpesaClientInterface {
                public array $calls = [];

                public function stkPush(
                    string $phone,
                    int    $amount,
                    string $reference,
                    string $description,
                    array  $callbackMeta = [],
                ): array {
                    $this->calls[] = compact('phone', 'amount', 'reference', 'description', 'callbackMeta');
                    return ['MerchantRequestID' => 'TEST-' . uniqid(), 'ResponseCode' => '0'];
                }
            };
        });
    }

    protected function getPackageProviders($app): array
    {
        return [MpesaCashierServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('mpesa-cashier.model', TestUser::class);
    }
}

// ---------------------------------------------------------------------------
// Minimal in-memory User model for tests
// ---------------------------------------------------------------------------

class TestUser extends Model
{
    use \FelixMuhoro\MpesaCashier\Billable;

    protected $table = 'users';
    protected $guarded = [];
    public $timestamps = true;
}
