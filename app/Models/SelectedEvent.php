<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelectedEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'view'
    ];

    public function events(): HasMany
    {
        return $this->hasMany(SelectedEventDatas::class, 'selected_event_id');
    }
}
