<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FrontBanner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'photo',
        'priority',
    ];
}
