<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfitSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_commision',
        'admin_fee_trx',
        'admin_fee_wd',
        'mul_pay_gate_fee',
        'tax_fee',
        'wa_quota_price_inv',
        'wa_quota_price_ntf',
    ];
}
