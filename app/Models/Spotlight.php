<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Spotlight extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'sub_title',
        'banner',
        'view'
    ];

    public function events(): HasMany
    {
        return $this->hasMany(SpotlightEvents::class, 'spotlight_id');
    }
}
