<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'service_id',
    'card_title',
    'detail_title',
    'slug',
    'icon',
    'image',
    'short_description',
    'overview',
    'benefits',
    'included_items',
    'tags',
    'audience_title',
    'audience_detail',
    'is_active',
    'meta_title',
    'meta_description',
    'meta_keywords',
    'sort_order',
])]
class ServiceOption extends Model
{
    protected $casts = [
        'benefits' => 'array',
        'included_items' => 'array',
        'tags' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ServiceOption $option) {
            if (blank($option->slug)) {
                $option->slug = Str::slug($option->card_title);
            }
        });

        static::updating(function (ServiceOption $option) {
            if ($option->isDirty('card_title')) {
                $option->slug = Str::slug($option->card_title);
            }
        });
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
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
}
