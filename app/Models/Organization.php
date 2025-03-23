<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'slug',
        'photo',
        'banner',
        'interest',
        'phone',
        'email',
        'linkedin',
        'instagram',
        'twitter',
        'whatsapp',
        'website',
        'desc',
        'allow_create_inv',
        'create_inv_quota',
        'deleted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'org_id')->where('deleted', 0);
    }

    public function eventsNoFilter(): HasMany
    {
        return $this->hasMany(Event::class, 'org_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdraw::class, 'org_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'org_id');
    }

    public function billAccs(): HasMany
    {
        return $this->hasMany(BillAccount::class, 'org_id');
    }

    public function credibilityData(): HasOne
    {
        return $this->hasOne(CredibilityOrg::class, 'org_id');
    }

    public function legality(): HasOne
    {
        return $this->hasOne(CredibilityOrg::class, 'org_id');
    }

    public function logAddInvQuota(): HasMany
    {
        return $this->hasMany(LogAddInvQuota::class, 'org_id');
    }
}
