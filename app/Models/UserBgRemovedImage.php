<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'tool_category_id', 'original_name', 'result_path', 'result_ext', 'original_size', 'result_size', 'expires_at'])]
class UserBgRemovedImage extends Model
{
    protected function casts(): array
    {
        return [
            'original_size' => 'integer',
            'result_size' => 'integer',
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

        return $name.'_bg_removed.'.$this->result_ext;
    }

    public function fileExists(): bool
    {
        return Storage::disk('public')->exists($this->result_path);
    }

    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::disk('public')->delete($this->result_path);
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

        return Storage::url($this->result_path);
    }
}
