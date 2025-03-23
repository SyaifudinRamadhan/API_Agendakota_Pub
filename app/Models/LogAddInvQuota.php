<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogAddInvQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'org_id',
        'type',
        'quota',
    ];

    /**
     * Get the Org that owns the LogAddInvQuota
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function org(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }
}
