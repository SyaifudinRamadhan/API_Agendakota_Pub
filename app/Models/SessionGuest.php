<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionGuest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'session_id',
        'guest_id'
    ];

    public function sesssion(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'session_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }
}
