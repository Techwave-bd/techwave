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
            $table->foreignId('service_option_id')->nullable()->after('service_id')->constrained('service_options')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_plans', function (Blueprint $table) {
            $table->dropForeign(['service_option_id']);
            $table->dropColumn('service_option_id');
        });
    }
};
