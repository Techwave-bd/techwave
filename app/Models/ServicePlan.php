<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'service_id',
    'name',
    'slug',
    'badge',
    'description',
    'price',
    'features',
    'buy_url',
    'sort_order',
    'is_active',
    'discount_price',
])]
class ServicePlan extends Model
{
    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'features' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function hasDiscount(): bool
    {
        return $this->discount_price !== null
            && (float) $this->discount_price > 0
            && (float) $this->discount_price < (float) $this->price;
    }

    public function finalPrice(): float
    {
        return $this->hasDiscount()
            ? (float) $this->discount_price
            : (float) $this->price;
    }

    public function discountPercentage(): int
    {
        if (!$this->hasDiscount()) {
            return 0;
        }

        return (int) round((1 - ((float) $this->discount_price / (float) $this->price)) * 100);
    }
}
