<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundData extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'ticket_id',
        'user_id',
        'event_id',
        'message',
        'bank_code',
        'account_name',
        'account_number',
        'phone_number',
        'percentage',
        'nominal',
        'basic_nominal',
        'finish',
        'approve_org',
        'approve_admin',
        'mode'
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
