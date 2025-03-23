<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomUrl extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'str_custom'
    ];

    public function event():BelongsTo{
        return $this->belongsTo(Event::class(), 'event_id');
    }
}
