<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tool_category_id', 'name', 'badge', 'description', 'monthly_price', 'yearly_price', 'max_file_upload', 'features', 'sort_order', 'is_active'])]
class ToolPlan extends Model
{
    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'max_file_upload' => 'integer',
            'features' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function toolCategory(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ToolSubscription::class);
    }
}
