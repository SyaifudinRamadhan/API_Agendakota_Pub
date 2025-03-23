<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    use HasFactory, HasUuids;

    protected $fillavble = [
        'event_id',
        'name',
        'email',
        'company',
        'job',
        'photo',
        'instagram',
        'linkedin',
        'twitter',
        'website',
        'overview'
    ];

    public function event(): BelongsTo{
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function sessionGuest(): HasMany{
        return $this->hasMany(SessionGuest::class, 'guest_id');
    }
}
