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
        Schema::create('tool_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_plan_id')->constrained()->cascadeOnDelete();
            $table->string('billing_cycle'); // monthly / yearly
            $table->string('transaction_id')->nullable();
            $table->string('sender_bkash')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('active'); // active / expired / cancelled / pending
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_subscriptions');
    }
};
