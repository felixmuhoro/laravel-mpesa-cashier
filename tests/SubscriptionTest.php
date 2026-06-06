<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCashier\Tests;

use FelixMuhoro\MpesaCashier\Exceptions\AlreadySubscribedException;
use FelixMuhoro\MpesaCashier\Exceptions\InvalidPlanException;
use FelixMuhoro\MpesaCashier\Invoice;
use FelixMuhoro\MpesaCashier\PlanBuilder;
use FelixMuhoro\MpesaCashier\Subscription;
use FelixMuhoro\MpesaCashier\SubscriptionManager;
use Illuminate\Support\Facades\Schema;

class SubscriptionTest extends TestCase
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

        PlanBuilder::define("basic-monthly")
            ->name("Basic Monthly")
            ->amount(500)
            ->monthly()
            ->trialDays(0)
            ->graceDays(3)
            ->register();

        PlanBuilder::define("pro-yearly")
            ->name("Pro Yearly")
            ->amount(4800)
            ->yearly()
            ->trialDays(14)
            ->graceDays(7)
            ->register();
    }

    public function test_user_can_subscribe_to_a_plan(): void
    {
        $user = TestUser::create([]);
        $subscription = $user->newSubscription("default", "basic-monthly")
            ->create("254712345678");

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals("active", $subscription->status);
        $this->assertEquals("basic-monthly", $subscription->plan_id);
        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_subscription_with_trial_sets_trialing_status(): void
    {
        $user = TestUser::create([]);
        $subscription = $user->newSubscription("default", "basic-monthly")
            ->withTrial(7)
            ->create("254712345678");

        $this->assertEquals("trialing", $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->trial_ends_at->isFuture());
    }

    public function test_duplicate_subscription_throws_exception(): void
    {
        $this->expectException(AlreadySubscribedException::class);
        $user = TestUser::create([]);
        $user->newSubscription("default", "basic-monthly")->create("254712345678");
        $user->newSubscription("default", "basic-monthly")->create("254712345678");
    }

    public function test_invalid_plan_throws_exception(): void
    {
        $this->expectException(InvalidPlanException::class);
        $user = TestUser::create([]);
        $user->newSubscription("default", "nonexistent-plan")->create("254712345678");
    }

    public function test_cancel_sets_cancelled_status_and_grace_period(): void
    {
        $user = TestUser::create([]);
        $sub  = $user->newSubscription("default", "basic-monthly")->create("254712345678");
        $sub->cancel();
        $this->assertEquals("cancelled", $sub->status);
        $this->assertNotNull($sub->grace_period_ends_at);
        $this->assertTrue($sub->onGracePeriod());
        $this->assertTrue($sub->active());
    }

    public function test_cancel_now_removes_grace_period(): void
    {
        $user = TestUser::create([]);
        $sub  = $user->newSubscription("default", "basic-monthly")->create("254712345678");
        $sub->cancelNow();
        $this->assertEquals("cancelled", $sub->status);
        $this->assertNull($sub->grace_period_ends_at);
        $this->assertFalse($sub->onGracePeriod());
        $this->assertFalse($sub->active());
    }

    public function test_subscription_can_be_resumed_in_grace_period(): void
    {
        $user = TestUser::create([]);
        $sub  = $user->newSubscription("default", "basic-monthly")->create("254712345678");
        $sub->cancel();
        $sub->resume();
        $this->assertEquals("active", $sub->status);
        $this->assertNull($sub->grace_period_ends_at);
    }

    public function test_resume_after_grace_period_throws_exception(): void
    {
        $this->expectException(\LogicException::class);
        $user = TestUser::create([]);
        $sub  = $user->newSubscription("default", "basic-monthly")->create("254712345678");
        $sub->cancelNow();
        $sub->resume();
    }

    public function test_invoice_is_created_on_subscription(): void
    {
        $user = TestUser::create([]);
        $user->newSubscription("default", "basic-monthly")->create("254712345678");
        $this->assertDatabaseCount("invoices", 1);
        $this->assertDatabaseHas("invoices", ["user_id" => $user->id, "amount" => 500, "status" => "pending"]);
    }

    public function test_payment_success_advances_billing_date_and_marks_active(): void
    {
        $user    = TestUser::create([]);
        $sub     = $user->newSubscription("default", "basic-monthly")->create("254712345678");
        $invoice = Invoice::where("subscription_id", $sub->id)->first();
        $oldDate = $sub->next_billing_date->copy();
        $manager = app(SubscriptionManager::class);
        $manager->handlePaymentSuccess(["subscription_id" => $sub->id, "invoice_id" => $invoice->id, "receipt" => "QGX3YZ8K1L"]);
        $sub->refresh(); $invoice->refresh();
        $this->assertEquals("active", $sub->status);
        $this->assertEquals(0, $sub->retry_count);
        $this->assertEquals("paid", $invoice->status);
        $this->assertTrue($sub->next_billing_date->greaterThan($oldDate));
    }

    public function test_payment_failure_increments_retry_count(): void
    {
        $user    = TestUser::create([]);
        $sub     = $user->newSubscription("default", "basic-monthly")->create("254712345678");
        $invoice = Invoice::where("subscription_id", $sub->id)->first();
        $manager = app(SubscriptionManager::class);
        $manager->handlePaymentFailure(["subscription_id" => $sub->id, "invoice_id" => $invoice->id]);
        $sub->refresh();
        $this->assertEquals(1, $sub->retry_count);
        $this->assertEquals("failed", $invoice->fresh()->status);
    }

    public function test_max_retries_marks_subscription_past_due(): void
    {
        config(["mpesa-cashier.retry_attempts" => 1]);
        $user    = TestUser::create([]);
        $sub     = $user->newSubscription("default", "basic-monthly")->create("254712345678");
        $invoice = Invoice::where("subscription_id", $sub->id)->first();
        $manager = app(SubscriptionManager::class);
        $manager->handlePaymentFailure(["subscription_id" => $sub->id, "invoice_id" => $invoice->id]);
        $sub->refresh();
        $this->assertEquals("past_due", $sub->status);
    }

    public function test_monthly_plan_advances_billing_by_one_month(): void
    {
        $user   = TestUser::create([]);
        $sub    = $user->newSubscription("default", "basic-monthly")->create("254712345678");
        $before = $sub->next_billing_date->copy();
        $sub->advanceBillingDate();
        $this->assertTrue($sub->next_billing_date->equalTo($before->addMonth()));
    }

    public function test_yearly_plan_advances_billing_by_one_year(): void
    {
        $user   = TestUser::create([]);
        $sub    = $user->newSubscription("default", "pro-yearly")->skipTrial()->create("254712345678");
        $before = $sub->next_billing_date->copy();
        $sub->advanceBillingDate();
        $this->assertTrue($sub->next_billing_date->equalTo($before->addYear()));
    }
}
