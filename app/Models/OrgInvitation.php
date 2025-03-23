<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgInvitation extends Model
{
    use HasFactory;
    protected $fillable = [
        'pch_id',
        'email',
        'wa_num',
        'name',
        'trx_img',
        'seen'
    ];

    public function purchase(): BelongsTo{
        return $this->belongsTo(Purchase::class, 'pch_id');
    }
}
