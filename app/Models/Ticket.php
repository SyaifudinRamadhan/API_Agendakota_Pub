<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'name',
        'cover',
        'desc',
        'type_price',
        'price',
        'quantity',
        'start_date',
        'end_date',
        'seat_number',
        'max_purchase',
        'seat_map',
        'deleted',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'ticket_id');
    }

    public function limitDaily(): HasOne
    {
        return $this->hasOne(DailyTicketLimit::class, 'ticket_id');
    }

    // public function vouchers(): HasMany{
    //     return $this->hasMany(Voucher::class, 'ticket_id');
    // }

    public function secretInfo(): HasOne
    {
        return $this->hasOne(SecretInfoTicket::class, 'ticket_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
