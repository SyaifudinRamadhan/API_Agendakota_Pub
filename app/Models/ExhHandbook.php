<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExhHandbook extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'exh_id',
        'file_name',
        'slug',
        'type_file'
    ];

    public function exh(): BelongsTo{
        return $this->belongsTo(Exhibitor::class, 'exh_id');
    }
}
