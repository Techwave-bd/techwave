<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'vcard_id',
    'user_id',
    'ip_address',
    'user_agent',
])]
class VcardScan extends Model
{
    public function vcard()
    {
        return $this->belongsTo(Vcard::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
