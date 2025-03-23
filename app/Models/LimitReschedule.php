<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LimitReschedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'limit_time'
    ];
}
