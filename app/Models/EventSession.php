<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'name',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'desc',
        'cover',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id')->where('deleted', 0);
    }
}
