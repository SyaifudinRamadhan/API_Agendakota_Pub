<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectedEventDatas extends Model
{
    use HasFactory;

    protected $fillable = [
        'selected_event_id',
        'event_id',
        'priority'
    ];

    public function selectedEvent(): BelongsTo
    {
        return $this->belongsTo(SelectedEvent::class, 'selected_event_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
