<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectedActivityDatas extends Model
{
    use HasFactory;
    protected $fillable = [
        "event_id", "selected_activity_id", "priority"
    ];
    public function selectedActivity(): BelongsTo
    {
        return $this->belongsTo(SelectedActivity::class, 'selected_activity_id');
    }
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, "event_id");
    }
}
