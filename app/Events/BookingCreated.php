<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->booking->loadMissing([
            'user',
            'service',
            'servicePlan',
            'pricingPlan',
        ]);
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('admin.bookings');
    }

    public function broadcastAs(): string
    {
        return 'booking.created';
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'booking_no' => $this->booking->booking_no,
            'booking_type' => $this->booking->booking_type,
            'status' => $this->booking->status,
            'customer_name' => $this->booking->full_name ?: $this->booking->user?->name,
            'title' => $this->bookingTitle(),
            'created_at' => $this->booking->created_at?->toDateTimeString(),
        ];
    }

    private function bookingTitle(): string
    {
        if ($this->booking->booking_type === 'pricing_plan') {
            return $this->booking->pricingPlan?->title
                ?? $this->booking->plan_name
                ?? 'Pricing Plan';
        }

        return $this->booking->service?->card_title
            ?? $this->booking->servicePlan?->name
            ?? $this->booking->plan_name
            ?? 'Service';
    }
}
