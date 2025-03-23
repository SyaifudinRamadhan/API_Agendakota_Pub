<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdraw extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'org_id',
        'bill_acc_id',
        'nominal',
        'commision',
        'admin_fee_wd',
        'basic_nominal',
        'status',
        'finish',
        'mode'
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function billAcc(): BelongsTo
    {
        return $this->belongsTo(BillAccount::class, 'bill_acc_id');
    }
}
