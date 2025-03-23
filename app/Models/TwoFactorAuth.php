<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TwoFactorAuth extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'ip_address',
        'otp_code',
        'state',
        'exp_to_verify'
    ];
}
