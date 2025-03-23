<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialDayEvents extends Model
{
    use HasFactory;

    protected $fillable = [
        'special_day_id',
        'event_id',
        'priority'
    ];

    public  function specialDay(): BelongsTo
    {
        return $this->belongsTo(SpecialDay::class, 'special_day_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
