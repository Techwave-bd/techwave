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
        Schema::create('user_compressed_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_category_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('compressed_path');
            $table->string('compressed_ext');
            $table->unsignedBigInteger('original_size');
            $table->unsignedBigInteger('compressed_size');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_compressed_images');
    }
};
