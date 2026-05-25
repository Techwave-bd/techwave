<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tool extends Model
{
    protected $fillable = ['tool_category_id', 'name', 'slug', 'description', 'icon', 'route', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function toolCategory(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }
}
