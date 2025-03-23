<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecretInfoTicket extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ticket_id',
        'meet_link',
        'desc'
    ];
    
}
