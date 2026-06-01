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
    'has_monthly_price',
    'monthly_price',
    'monthly_discount_price',
    'monthly_buy_url',
    'has_yearly_price',
    'yearly_price',
    'yearly_discount_price',
    'yearly_buy_url',
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
        'has_monthly_price' => 'boolean',
        'monthly_price' => 'decimal:2',
        'monthly_discount_price' => 'decimal:2',
        'has_yearly_price' => 'boolean',
        'yearly_price' => 'decimal:2',
        'yearly_discount_price' => 'decimal:2',
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
        if (! $this->hasDiscount()) {
            return 0;
        }

        return (int) round((1 - ((float) $this->discount_price / (float) $this->price)) * 100);
    }

    public function hasMonthlyDiscount(): bool
    {
        return $this->has_monthly_price
            && $this->monthly_price !== null
            && $this->monthly_discount_price !== null
            && (float) $this->monthly_discount_price > 0
            && (float) $this->monthly_discount_price < (float) $this->monthly_price;
    }

    public function finalMonthlyPrice(): ?float
    {
        if (! $this->has_monthly_price || $this->monthly_price === null) {
            return null;
        }

        return $this->hasMonthlyDiscount()
            ? (float) $this->monthly_discount_price
            : (float) $this->monthly_price;
    }

    public function monthlyDiscountPercentage(): int
    {
        if (! $this->hasMonthlyDiscount()) {
            return 0;
        }

        return (int) round((1 - ((float) $this->monthly_discount_price / (float) $this->monthly_price)) * 100);
    }

    public function hasYearlyDiscount(): bool
    {
        return $this->has_yearly_price
            && $this->yearly_price !== null
            && $this->yearly_discount_price !== null
            && (float) $this->yearly_discount_price > 0
            && (float) $this->yearly_discount_price < (float) $this->yearly_price;
    }

    public function finalYearlyPrice(): ?float
    {
        if (! $this->has_yearly_price || $this->yearly_price === null) {
            return null;
        }

        return $this->hasYearlyDiscount()
            ? (float) $this->yearly_discount_price
            : (float) $this->yearly_price;
    }

    public function yearlyDiscountPercentage(): int
    {
        if (! $this->hasYearlyDiscount()) {
            return 0;
        }

        return (int) round((1 - ((float) $this->yearly_discount_price / (float) $this->yearly_price)) * 100);
    }

    public function hasRecurringPrice(): bool
    {
        return $this->has_monthly_price || $this->has_yearly_price;
    }

    public function hasBothMonthlyAndYearly(): bool
    {
        return $this->has_monthly_price && $this->has_yearly_price;
    }
}
