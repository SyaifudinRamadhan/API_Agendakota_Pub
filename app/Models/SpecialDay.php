<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpecialDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'view'
    ];

    public function events(): HasMany
    {
        return $this->hasMany(SpecialDayEvents::class, 'special_day_id');
    }
}
