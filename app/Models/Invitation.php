<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'target_user_id',
        'pch_id',
        'response'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function userTarget(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'pch_id');
    }
}
