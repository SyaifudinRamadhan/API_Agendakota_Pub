<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillAccount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'org_id',
        'bank_name',
        'acc_name',
        'acc_number',
        'status',
        'otp',
        'exp_to_verify',
        'deleted'
    ];

    public function org(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function withdraws(): HasMany
    {
        return $this->hasMany(Withdraw::class, 'bill_acc_id');
    }
}
