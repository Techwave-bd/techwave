<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_name', 'logo', 'phone', 'address', 'website'])]
class Company extends Model
{
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
