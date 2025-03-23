<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\ResetPasssword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'f_name',
        'l_name',
        'name',
        'email',
        'password',
        'g_id',
        'photo',
        'is_active',
        'phone',
        'interest',
        'linkedin',
        'instagram',
        'twitter',
        'whatsapp',
        'two_factor',
        "deleted"
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function admin(): HasOne
    {
        return $this->hasOne(Admin::class, 'user_id');
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'user_id');
    }

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class, 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'user_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'user_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'user_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'user_id');
    }

    public function otp(): HasOne
    {
        return $this->hasOne(Otp::class, 'user_id');
    }

    public function sendPasswordResetNotification($token)
    {
        $url = env('FRONTEND_URL') . '/reset-password/'. $token;
        $this->notify(new ResetPasssword($this->name, $url));
    }
}
