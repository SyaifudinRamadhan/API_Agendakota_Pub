<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Handbook extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'file_name',
        'slug',
        'type_file'
    ];

    public function event(): BelongsTo{
        return $this->belongsTo(Event::class, 'event_id');
    }
}
