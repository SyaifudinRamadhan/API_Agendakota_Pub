<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ReservedSeat extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        "pch_id",
        "seat_number"
    ];
}
