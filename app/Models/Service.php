<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['category_id', 'subcategory_id', 'card_title', 'detail_title', 'slug', 'icon', 'image', 'short_description', 'overview', 'benefits', 'included_items', 'tags', 'audience_title', 'audience_detail', 'is_active', 'is_featured', 'meta_title', 'meta_description', 'meta_keywords'])]
class Service extends Model
{
    protected $casts = [
        'benefits' => 'array',
        'included_items' => 'array',
        'tags' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            if (blank($service->slug)) {
                $service->slug = Str::slug($service->card_title);
            }
        });

        static::updating(function (Service $service) {
            if ($service->isDirty('card_title')) {
                $service->slug = Str::slug($service->card_title);
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

        public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

        public function serviceOptions()
    {
        return $this->hasMany(ServiceOption::class)->orderBy('sort_order');
    }

    public function servicePlans()
    {
        return $this->hasMany(ServicePlan::class);
    }

    public function activePlans()
    {
        return $this->hasMany(ServicePlan::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function assignedUsers()
    {
        return $this->hasMany(UserService::class);
    }
}
