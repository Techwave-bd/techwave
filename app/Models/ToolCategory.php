<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'icon', 'image', 'description', 'sort_order', 'is_active', 'free_max_file_upload'])]
class ToolCategory extends Model
{
    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'free_max_file_upload' => 'integer',
    ];

    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class, 'tool_category_id')->where('is_active', true)->orderBy('sort_order');
    }

    public function toolPlans()
    {
        return $this->hasMany(ToolPlan::class, 'tool_category_id');
    }

    public function activePlans()
    {
        return $this->hasMany(ToolPlan::class, 'tool_category_id')->where('is_active', true)->orderBy('sort_order');
    }

    public function subscriptions()
    {
        return $this->hasMany(ToolSubscription::class, 'tool_category_id');
    }
}
