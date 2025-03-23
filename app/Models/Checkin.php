<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkin extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'pch_id',
        'event_id',
        'status'
    ];

    public function pch(): BelongsTo{
        return $this->belongsTo(Purchase::class, 'pch_id');
    }

    public function event(): BelongsTo{
        return $this->belongsTo(Event::class, 'event_id');
    }
}
