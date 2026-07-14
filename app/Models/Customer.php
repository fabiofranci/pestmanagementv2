<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'legacy_customer_code',
        'name',
        'legal_name',
        'tax_id',
        'vat_number',
        'fiscal_code',
        'email',
        'phone',
        'secondary_phone',
        'mobile',
        'pec',
        'sdi_code',
        'address',
        'city',
        'postcode',
        'province',
        'country',
        'notes',
        'status',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(CustomerSite::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
