<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'service_id',
    'service_plan_id',
    'full_name',
    'phone',
    'email',
    'company_name',
    'message',
    'status',
])]
class ServiceBooking extends Model
{
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function servicePlan()
    {
        return $this->belongsTo(ServicePlan::class);
    }

    public function assignedService()
    {
        return $this->hasOne(UserService::class);
    }
}
