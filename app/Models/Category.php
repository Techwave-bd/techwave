<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'icon', 'image', 'description', 'sort_order', 'is_active'])]
class Category extends Model
{
    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function blogs()
    {
        return $this->hasMany(Blog::class);
    }
}
