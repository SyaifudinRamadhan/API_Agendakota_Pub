<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkgPayment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'pkg_id',
        'token_trx',
        'pay_state',
        'order_id',
        'price'
    ];

    public function event(): BelongsTo{
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function package(): BelongsTo{
        return $this->belongsTo(PkgPricing::class, 'pkg_id');
    }
}
