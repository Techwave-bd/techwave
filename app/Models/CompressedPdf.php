<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id',
    'session_id',
    'original_name',
    'original_path',
    'original_size',
    'compressed_path',
    'compressed_size',
    'compression_level',
    'no_reduction',
    'is_backup_enabled',
    'status',
    'error_message',
    'job_id',
    'processed_at',
    'expires_at',
    'backup_expires_at',
])]
class CompressedPdf extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'original_size' => 'integer',
            'compressed_size' => 'integer',
            'no_reduction' => 'boolean',
            'is_backup_enabled' => 'boolean',
            'processed_at' => 'datetime',
            'expires_at' => 'datetime',
            'backup_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePremiumBackups($query)
    {
        return $query
            ->where('is_backup_enabled', true)
            ->whereNotNull('backup_expires_at')
            ->where('backup_expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function isBackupExpired(): bool
    {
        return $this->backup_expires_at !== null
            && $this->backup_expires_at->isPast();
    }

    public function hasActiveBackup(): bool
    {
        return $this->is_backup_enabled
            && $this->backup_expires_at !== null
            && ! $this->isBackupExpired();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return in_array(
            $this->status,
            ['pending', 'processing'],
            true
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function belongsToCurrentVisitor(): bool
    {
        if ($this->user_id !== null) {
            return auth()->check()
                && auth()->id() === $this->user_id;
        }

        if ($this->session_id === null) {
            return false;
        }

        return hash_equals(
            (string) $this->session_id,
            (string) session()->getId()
        );
    }

    public function originalFileExists(): bool
    {
        if (empty($this->original_path)) {
            return false;
        }

        return Storage::disk(
            config('pdf-compressor.storage_disk')
        )->exists($this->original_path);
    }

    public function compressedFileExists(): bool
    {
        if (empty($this->compressed_path)) {
            return false;
        }

        return Storage::disk(
            config('pdf-compressor.storage_disk')
        )->exists($this->compressed_path);
    }

    public function downloadableFileExists(): bool
    {
        if ($this->no_reduction) {
            return $this->originalFileExists();
        }

        return $this->compressedFileExists();
    }

    public function downloadablePath(): ?string
    {
        if ($this->no_reduction) {
            return $this->original_path;
        }

        return $this->compressed_path;
    }

    public function deleteOriginalFile(): bool
    {
        if ($this->originalFileExists()) {
            return Storage::disk(
                config('pdf-compressor.storage_disk')
            )->delete($this->original_path);
        }

        return true;
    }

    public function deleteCompressedFile(): bool
    {
        if ($this->compressedFileExists()) {
            return Storage::disk(
                config('pdf-compressor.storage_disk')
            )->delete($this->compressed_path);
        }

        return true;
    }

    public function deleteAllFiles(): void
    {
        $this->deleteOriginalFile();
        $this->deleteCompressedFile();
    }

    public function downloadName(): string
    {
        $name = pathinfo(
            $this->original_name,
            PATHINFO_FILENAME
        );

        if ($this->no_reduction) {
            return $name.'.pdf';
        }

        return $name.'_compressed.pdf';
    }

    public function savingsPercent(): int
    {
        if (! $this->original_size || $this->original_size <= 0) {
            return 0;
        }

        if (
            ! $this->compressed_size
            || $this->compressed_size >= $this->original_size
        ) {
            return 0;
        }

        return (int) round(
            (1 - ($this->compressed_size / $this->original_size)) * 100
        );
    }

    public function savedBytes(): int
    {
        if (! $this->original_size || ! $this->compressed_size) {
            return 0;
        }

        return max(
            0,
            $this->original_size - $this->compressed_size
        );
    }
}
