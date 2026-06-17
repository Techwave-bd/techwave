<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'slug',
    'description',
    'preview_image',
    'layout',
    'brand_color',
    'html_template',
    'css_styles',
    'is_paid',
    'is_active',
    'sort_order'
])]
class InvoiceTheme extends Model
{
    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function isAccessibleBy(?User $user): bool
    {
        $category = ToolCategory::where('slug', 'business-tools')->first();

    return ! $this->is_paid || ($category && $user?->hasActiveToolSubscription($category) ?? false);
    }

    public function usesCustomTemplate(): bool
    {
        return filled($this->html_template);
    }
}
