<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                  ->constrained('subscriptions')
                  ->cascadeOnDelete();

            $table->unsignedBigInteger('user_id')->index();
            $table->string('invoice_number')->unique();

            $table->unsignedInteger('amount');          // in KES whole units
            $table->string('currency', 10)->default('KES');

            // Status: pending | paid | failed
            $table->string('status')->default('pending');

            $table->string('mpesa_receipt')->nullable(); // e.g. QGX3YZ8K1L
            $table->string('phone', 20)->nullable();

            $table->timestamp('due_date');
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['subscription_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
