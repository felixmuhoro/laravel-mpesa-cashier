<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // Owner
            $table->unsignedBigInteger('user_id')->index();

            // Subscription metadata
            $table->string('name')->default('default');       // named subscription slot
            $table->string('plan_id');

            // Status: active | cancelled | past_due | trialing
            $table->string('status')->default('active');

            // Billing dates
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();         // set on cancel
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->timestamp('next_billing_date')->nullable();

            // Retry tracking for failed renewals
            $table->unsignedTinyInteger('retry_count')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'name']);
            $table->index(['status', 'next_billing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
