<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisburstmentWd extends Model
{
    use HasFactory;

    protected $fillable = [
        'disburstment_id',
        'withdraw_id'
    ];

    public function FunctionName(): BelongsTo
    {
        return $this->belongsTo(Withdraw::class, 'withdraw_id');
    }
}
