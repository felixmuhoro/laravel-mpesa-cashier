<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')
                  ->constrained('subscriptions')
                  ->cascadeOnDelete();
            $table->string('plan_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->index(['subscription_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};
