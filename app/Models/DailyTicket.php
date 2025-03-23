<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTicket extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        "purchase_id", "visit_date"
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }
}
