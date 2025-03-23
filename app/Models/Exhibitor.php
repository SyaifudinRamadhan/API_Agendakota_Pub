<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exhibitor extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'name',
        'email',
        'category',
        'address',
        'instagram',
        'linkedin',
        'twitter',
        'website',
        'virtual_booth',
        'booth_link',
        'logo',
        'booth_image',
        'phone',
        'description',
        'video',
        'overview'
    ];

    public function event(): BelongsTo{
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function handbooks(): HasMany{
        return  $this->hasMany(ExhHandbook::class, 'exh_id');
    }

    public function products(): HasMany{
        return $this->hasMany(ExhProduct::class, 'exh_id');
    }
}
