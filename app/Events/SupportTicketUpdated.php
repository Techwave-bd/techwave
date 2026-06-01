<?php

namespace App\Events;

use App\Models\SupportTicket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTicketUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $ticketId;

    public ?int $userId;

    public string $action;

    public function __construct(SupportTicket $ticket, string $action = 'updated')
    {
        $this->ticketId = $ticket->id;
        $this->userId = $ticket->user_id;
        $this->action = $action;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('admin.tickets'),
            new PrivateChannel('ticket.'.$this->ticketId),
        ];

        if ($this->userId) {
            $channels[] = new PrivateChannel('user.'.$this->userId.'.tickets');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'user_id' => $this->userId,
            'action' => $this->action,
        ];
    }
}
