<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('order_no')->unique();

            $table->enum('order_type', [
                'service',
                'pricing_plan',
            ])->default('service');

            $table->foreignId('service_id')
                ->nullable()
                ->constrained('services')
                ->nullOnDelete();

            $table->foreignId('service_plan_id')
                ->nullable()
                ->constrained('service_plans')
                ->nullOnDelete();

            $table->foreignId('pricing_plan_id')
                ->nullable()
                ->constrained('pricing_plans')
                ->nullOnDelete();

            $table->enum('billing_cycle', [
                'one_time',
                'monthly',
                'yearly',
                'custom',
            ])->nullable();

            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('company_name')->nullable();
            $table->string('company_phone')->nullable();
            $table->string('company_email')->nullable();

            $table->string('plan_name')->nullable();

            $table->decimal('plan_price', 10, 2)->nullable();
            $table->decimal('requested_price', 10, 2)->nullable();
            $table->decimal('quoted_price', 10, 2)->nullable();
            $table->decimal('final_price', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->default(0);

            $table->string('currency')->default('BDT');

            $table->text('message')->nullable();
            $table->text('user_note')->nullable();
            $table->text('admin_note')->nullable();

            $table->enum('status', [
                'pending',
                'awaiting_payment',
                'paid',
                'active',
                'completed',
                'cancelled',
            ])->default('awaiting_payment');

            $table->timestamps();

            $table->index(['order_type', 'status']);
            $table->index('booking_id');
            $table->index('user_id');
            $table->index('pricing_plan_id');
            $table->index(['service_id', 'service_plan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
