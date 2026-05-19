<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'booking_no',
    'booking_type',
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
    'currency',
    'message',
    'user_note',
    'admin_note',
    'status',
    'pricing_order_id',
    'admin_read_at',
])]
class Booking extends Model
{
    protected $casts = [
        'plan_price' => 'decimal:2',
        'requested_price' => 'decimal:2',
        'quoted_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'admin_read_at' => 'datetime',
    ];

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

    public function order()
    {
        return $this->hasOne(Order::class);
    }

    public function pricingOrder()
    {
        return $this->belongsTo(PricingOrder::class);
    }

    // public function assignedService()
    // {
    //     return $this->hasOne(UserService::class);
    // }

    public function isServiceBooking(): bool
    {
        return $this->booking_type === 'service';
    }

    public function isPricingPlanBooking(): bool
    {
        return $this->booking_type === 'pricing_plan';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isQuoted(): bool
    {
        return $this->status === 'quoted';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
