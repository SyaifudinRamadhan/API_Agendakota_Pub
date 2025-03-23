<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Event extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'org_id',
        'slug',
        'name',
        'category',
        'topics',
        'logo',
        'desc',
        'snk',
        'exe_type',
        'location',
        'province',
        'city',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'is_publish',
        'instagram',
        'twitter',
        'website',
        'twn_url',
        'custom_fields',
        'seat_map',
        'single_trx',
        'visibility',
        'allow_refund',
        'deleted',
    ];

    public function org(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class, 'event_id')->where('deleted', 0);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(PkgPayment::class, 'event_id');
    }

    public function withdraw(): HasOne
    {
        return $this->hasOne(Withdraw::class, 'event_id');
    }

    public function breakdowns(): HasMany
    {
        return $this->hasMany(Breakdown::class, 'event_id');
    }

    public function exhs(): HasMany
    {
        return $this->hasMany(Exhibitor::class, 'event_id');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class, 'event_id');
    }

    public function handbooks(): HasMany
    {
        return $this->hasMany(Handbook::class, 'event_id');
    }

    public function receptionists(): HasMany
    {
        return $this->hasMany(Receptionist::class, 'event_id');
    }

    public function slugCustoms(): HasMany
    {
        return $this->hasMany(SlugCustom::class, 'event_id');
    }

    public function sponsors(): HasMany
    {
        return $this->hasMany(Sponsor::class, 'event_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'event_id');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class, 'event_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'event_id')->where('deleted', 0)->orderBy('price', 'ASC');
    }

    public function ticketsNonFilter(): HasMany
    {
        return $this->hasMany(Ticket::class, 'event_id');   
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(CustomFieldSurvey::class, 'event_id');
    }

    public function availableDays(): HasMany
    {
        return $this->hasMany(AvailableDayTicketSell::class, "event_id");
    }

    public function mailAttandances(): HasMany
    {
        return $this->hasMany(MailAttandance::class, 'event_id');
    }

    public function availableReschedule(): HasOne
    {
        return $this->hasOne(LimitReschedule::class, 'event_id');
    }

    public function thumbnails(): HasMany
    {
        return $this->hasMany(EventThumbnail::class, 'event_id');
    }
}