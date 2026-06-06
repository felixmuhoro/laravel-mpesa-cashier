<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier\Tests;

use FelixMuhoro\MpesaCashier\PlanBuilder;
use Illuminate\Support\Facades\Schema;

class BillableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create("users", function ($table) {
            $table->id();
            $table->string("name")->default("Test User");
            $table->string("phone")->default("254712345678");
            $table->timestamps();
        });

        $this->artisan("migrate", ["--path" => "database/migrations", "--realpath" => true]);

        PlanBuilder::define("starter")
            ->name("Starter")
            ->amount(300)
            ->monthly()
            ->graceDays(3)
            ->register();
    }

    public function test_subscribed_returns_false_for_new_user(): void
    {
        $user = TestUser::create([]);
        $this->assertFalse($user->subscribed());
    }

    public function test_subscribed_returns_true_after_subscription(): void
    {
        $user = TestUser::create([]);
        $user->newSubscription("default", "starter")->create("254712345678");
        $this->assertTrue($user->subscribed());
    }

    public function test_subscribed_checks_specific_plan(): void
    {
        $user = TestUser::create([]);
        $user->newSubscription("default", "starter")->create("254712345678");
        $this->assertTrue($user->subscribed("default", "starter"));
        $this->assertFalse($user->subscribed("default", "nonexistent"));
    }

    public function test_on_grace_period_returns_false_for_active_subscription(): void
    {
        $user = TestUser::create([]);
        $user->newSubscription("default", "starter")->create("254712345678");
        $this->assertFalse($user->onGracePeriod());
    }

    public function test_on_grace_period_returns_true_after_cancel(): void
    {
        $user = TestUser::create([]);
        $user->newSubscription("default", "starter")->create("254712345678");
        $user->cancelSubscription();
        $this->assertTrue($user->onGracePeriod());
    }

    public function test_cancel_now_via_billable(): void
    {
        $user = TestUser::create([]);
        $user->newSubscription("default", "starter")->create("254712345678");
        $sub = $user->cancelNow();
        $this->assertEquals("cancelled", $sub->status);
        $this->assertFalse($user->subscribed());
    }

    public function test_resume_via_billable(): void
    {
        $user = TestUser::create([]);
        $user->newSubscription("default", "starter")->create("254712345678");
        $user->cancelSubscription();
        $sub = $user->resume();
        $this->assertEquals("active", $sub->status);
        $this->assertFalse($user->onGracePeriod());
    }

    public function test_shorthand_subscribe_method(): void
    {
        $user = TestUser::create(["phone" => "254712345678"]);
        $sub  = $user->subscribe("starter");
        $this->assertEquals("starter", $sub->plan_id);
        $this->assertEquals("active", $sub->status);
    }

    public function test_on_trial_returns_correct_value(): void
    {
        $user = TestUser::create([]);
        $user->newSubscription("default", "starter")->withTrial(7)->create("254712345678");
        $this->assertTrue($user->onTrial());
    }

    public function test_multiple_named_subscriptions(): void
    {
        $user = TestUser::create([]);
        $user->newSubscription("primary", "starter")->create("254712345678");
        $user->newSubscription("addon", "starter")->create("254712345678");
        $this->assertTrue($user->subscribed("primary"));
        $this->assertTrue($user->subscribed("addon"));
        $this->assertFalse($user->subscribed("nonexistent"));
    }

    public function test_subscription_method_returns_correct_model(): void
    {
        $user    = TestUser::create([]);
        $created = $user->newSubscription("default", "starter")->create("254712345678");
        $fetched = $user->subscription("default");
        $this->assertNotNull($fetched);
        $this->assertEquals($created->id, $fetched->id);
    }

    public function test_invoices_relationship(): void
    {
        $user = TestUser::create([]);
        $user->newSubscription("default", "starter")->create("254712345678");
        $this->assertCount(1, $user->invoices);
    }
}
