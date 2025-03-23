<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'name',
        'code',
        'discount',
        'quantity',
        'start',
        'end',
    ];

    public function event(): BelongsTo{
        return $this->belongsTo(Event::class, 'event_id')->where('deleted', 0);
    }
    public function eventNoFilter(): BelongsTo{
        return $this->belongsTo(Event::class, 'event_id');
    }
    public function forTickets(): HasMany{
        return $this->hasMany(VoucherTiket::class, 'voucher_id');
    }
}
