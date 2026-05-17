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
        Schema::create('vat_settings', function (Blueprint $table) {
            $table->id();
            $table->string('apply_to')->default('both');
            $table->string('title')->default('VAT');
            $table->boolean('is_enabled')->unique();
            $table->decimal('percentage', 8, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_settings');
    }
};
