<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['category_id', 'name', 'slug', 'icon', 'image', 'description', 'sort_order', 'is_active'])]
class Subcategory extends Model
{
    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function servicePlans()
    {
        return $this->hasMany(ServicePlan::class);
    }
}
