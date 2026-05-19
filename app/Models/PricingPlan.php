<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['plan_type', 'title', 'icon', 'description', 'monthly_price', 'yearly_price', 'features', 'status', 'purchase_count', 'monthly_discount_price', 'yearly_discount_price',])]
class PricingPlan extends Model
{
    protected $casts = [
        'monthly_price' => 'decimal:2',
        'monthly_discount_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'yearly_discount_price' => 'decimal:2',
        'features' => 'array',
        'purchase_count' => 'integer',
    ];
}
