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
        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->decimal('monthly_discount_price', 10, 2)->nullable()->after('monthly_price');
            $table->decimal('yearly_discount_price', 10, 2)->nullable()->after('yearly_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->dropColumn(['monthly_discount_price', 'yearly_discount_price']);
        });
    }
};
