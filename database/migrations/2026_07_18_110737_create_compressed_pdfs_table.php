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
        Schema::create('compressed_pdfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('original_name');
            $table->string('original_path');
            $table->unsignedBigInteger('original_size');
            $table->string('compressed_path')->nullable();
            $table->unsignedBigInteger('compressed_size')->nullable();
            $table->string('compression_level', 30);
            $table->boolean('no_reduction')->default(false);
            $table->boolean('is_backup_enabled')->default(false);
            $table->string('status', 30)->default('pending');
            $table->text('error_message')->nullable();
            $table->string('job_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('backup_expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['session_id', 'status']);
            $table->index(['is_backup_enabled', 'backup_expires_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compressed_pdfs');
    }
};
