<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedTrxNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_target',
        'mail_sec_target',
        'fn_path',
        'type',
        'str_data'
    ];
}
