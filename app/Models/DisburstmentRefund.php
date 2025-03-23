<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisburstmentRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'disburstment_id',
        'str_refund_ids'
    ];
}
