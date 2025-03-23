<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PkgPricing extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name','description','ticket_commission','session_count',
        'custom_link','sponsor_count','exhibitor_count','partner_media_count',
        'report_download','max_attachment','price','deleted'
    ];

    public function payments(): HasMany{
        return $this->hasMany(PkgPayment::class, 'pkg_id');
    }
}
