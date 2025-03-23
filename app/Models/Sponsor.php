<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sponsor extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'type',
        'name',
        'logo',
        'priority',
        'website'
    ];

    public function event(): BelongsTo{
        return $this->belongsTo(Event::class, 'event_id');
    }
}
