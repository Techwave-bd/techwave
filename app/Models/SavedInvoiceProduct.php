<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'name',
    'type',
    'unit',
    'description',
    'unit_price',
    'tax_rate',
    'discount_price',
    'purchase_price',
    'stock_count'
])]
class SavedInvoiceProduct extends Model
{
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'stock_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
