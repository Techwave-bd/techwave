<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'tool_category_id', 'original_name', 'compressed_path', 'compressed_ext', 'original_size', 'compressed_size', 'expires_at'])]
class UserCompressedImage extends Model
{
    protected function casts(): array
    {
        return [
            'original_size' => 'integer',
            'compressed_size' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toolCategory(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function downloadName(): string
    {
        $name = pathinfo($this->original_name, PATHINFO_FILENAME);

        return $name.'_compressed.'.$this->compressed_ext;
    }

    public function fileExists(): bool
    {
        return Storage::disk('public')->exists($this->compressed_path);
    }

    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::disk('public')->delete($this->compressed_path);
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function previewUrl(): ?string
    {
        if (! $this->fileExists()) {
            return null;
        }

        return route('storage.compressed-images', $this->compressed_path);
    }
}
