<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViralCity extends Model
{
    use HasFactory;

    protected $fillable = [
        'city_id'
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }
}
