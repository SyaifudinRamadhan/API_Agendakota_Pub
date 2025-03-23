<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelectedActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        "title", "view"
    ];

    public function events(): HasMany
    {
        return $this->hasMany(SelectedActivityDatas::class, 'selected_activity_id');
    }
}
