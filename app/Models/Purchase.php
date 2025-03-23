<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Purchase extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'pay_id',
        'ticket_id',
        'code',
        'amount',
        'tax_amount',
        'is_mine',
        'org_inv',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'pay_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function inivitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'pch_id');
    }

    public function checkin(): HasOne
    {
        return $this->hasOne(Checkin::class, 'pch_id');
    }

    public function visitDate(): HasOne
    {
        return $this->hasOne(DailyTicket::class, 'purchase_id');
    }

    public function seatNumber(): HasOne
    {
        return $this->hasOne(ReservedSeat::class, 'pch_id');
    }

    public function orgInv(): HasOne
    {
        return $this->hasOne(OrgInvitation::class, 'pch_id');
    }

    public function orgInvData(): HasOne
    {
        return $this->hasOne(OrgInvitation::class, 'pch_id');
    }
}
