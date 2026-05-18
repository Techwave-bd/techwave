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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('booking_no')->unique();

            $table->enum('booking_type', [
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
            $table->string('currency')->default('BDT');

            $table->text('message')->nullable();
            $table->text('user_note')->nullable();
            $table->text('admin_note')->nullable();

            $table->enum('status', [
                'pending',
                'quoted',
                'accepted',
                'rejected',
                'converted',
                'cancelled',
            ])->default('pending');

            $table->foreignId('pricing_order_id')
                ->nullable()
                ->constrained('pricing_orders')
                ->nullOnDelete();

            $table->timestamp('admin_read_at')->nullable();

            $table->timestamps();

            $table->index(['booking_type', 'status']);
            $table->index(['service_id', 'service_plan_id']);
            $table->index('pricing_plan_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
