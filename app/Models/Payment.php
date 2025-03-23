<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'token_trx',
        'pay_state',
        'order_id',
        'price',
        'admin_fee',
        'platform_fee',
        'code_method',
        'virtual_acc',
        'qr_str',
        'pay_links',
        'expired',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'pay_id');
    }
}
