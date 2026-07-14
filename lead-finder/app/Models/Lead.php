<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'company_name',
        'slug',
        'vat_number',
        'fiscal_code',
        'website',
        'email',
        'phone',
        'mobile',
        'whatsapp',
        'pec',
        'region',
        'province',
        'city',
        'address',
        'sector',
        'score',
        'status',
        'source_name',
        'source_url',
        'last_seen_at',
        'verified_at',
        'email_marketing_allowed',
        'whatsapp_marketing_allowed',
        'phone_contact_allowed',
        'opted_out_at',
        'blacklisted_at',
        'notes',
    ];

    protected $casts = [
        'email_marketing_allowed' => 'boolean',
        'whatsapp_marketing_allowed' => 'boolean',
        'phone_contact_allowed' => 'boolean',
        'last_seen_at' => 'datetime',
        'verified_at' => 'datetime',
        'opted_out_at' => 'datetime',
        'blacklisted_at' => 'datetime',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(LeadContact::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }
    //
}
