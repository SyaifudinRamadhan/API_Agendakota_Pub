<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredibilityOrg extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        "org_id",
        "type_legality",
        "tax_id_number",
        "tax_image",
        "company_name",
        "address",
        'business_entity',
        'pic_name',
        'pic_nic',
        'pic_nic_image',
        'company_phone',
        'status'
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }
}
