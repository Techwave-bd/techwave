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
        Schema::table('service_plans', function (Blueprint $table) {
            $table->string('monthly_buy_url')->nullable()->after('buy_url');
            $table->string('yearly_buy_url')->nullable()->after('monthly_buy_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_plans', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_buy_url',
                'yearly_buy_url',
            ]);
        });
    }
};
