<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExhProduct extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'exh_id',
        'name',
        'price',
        'image',
        'url'
    ];

    public function exh(): BelongsTo{
        return $this->belongsTo(Exhibitor::class, 'exh_id');
    }
}
