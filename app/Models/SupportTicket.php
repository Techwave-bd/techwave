<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'ticket_no',
    'subject',
    'customer_name',
    'customer_email',
    'customer_phone',
    'department',
    'priority',
    'status',
    'message',
    'last_reply_at',
    'admin_read_at',
    'client_read_at',
    'closed_at',
])]
class SupportTicket extends Model
{
    protected $casts = [
        'last_reply_at' => 'datetime',
        'admin_read_at' => 'datetime',
        'client_read_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replies()
    {
        return $this->hasMany(SupportTicketReply::class);
    }

    public function attachments()
    {
        return $this->hasMany(SupportTicketAttachment::class);
    }

    public static function generateTicketNo(): string
    {
        $nextId = (static::query()->max('id') ?? 0) + 1;

        return 'TKT-' . now()->format('Y') . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
