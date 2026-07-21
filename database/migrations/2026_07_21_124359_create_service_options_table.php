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
        Schema::create('service_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();

            $table->string('card_title');
            $table->string('detail_title');
            $table->string('slug')->unique();

            $table->string('icon')->nullable();
            $table->string('image')->nullable();

            $table->text('short_description');
            $table->longText('overview')->nullable();

            $table->json('benefits')->nullable();
            $table->json('included_items')->nullable();
            $table->json('tags')->nullable();

            $table->string('audience_title')->nullable();
            $table->text('audience_detail')->nullable();

            $table->boolean('is_active')->default(true);

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_options');
    }
};
