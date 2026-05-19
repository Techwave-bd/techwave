<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'booking_id',
    'user_id',
    'order_no',
    'order_type',

    'service_id',
    'service_plan_id',
    'pricing_plan_id',

    'billing_cycle',

    'full_name',
    'phone',
    'email',

    'company_name',
    'company_phone',
    'company_email',

    'plan_name',
    'plan_price',
    'requested_price',
    'quoted_price',
    'final_price',
    'amount',
    'currency',

    'message',
    'user_note',
    'admin_note',

    'status',
])]
class Order extends Model
{
    protected $casts = [
        'plan_price' => 'decimal:2',
        'requested_price' => 'decimal:2',
        'quoted_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function servicePlan()
    {
        return $this->belongsTo(ServicePlan::class);
    }

    public function pricingPlan()
    {
        return $this->belongsTo(PricingPlan::class);
    }
}
