<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpotlightEvents extends Model
{
    use HasFactory;

    protected $fillable = [
        'spotlight_id',
        'event_id',
        'priority'
    ];

    public function spotlight(): BelongsTo
    {
        return $this->belongsTo(Spotlight::class, 'spotlight_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
