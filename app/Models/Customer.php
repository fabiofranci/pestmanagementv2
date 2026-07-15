<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'customer_group_id',
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

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            if (
                $customer->isDirty('vat_number')
                || $customer->isDirty('fiscal_code')
                || blank($customer->tax_id)
            ) {
                $customer->tax_id = $customer->vat_number ?: $customer->fiscal_code ?: $customer->tax_id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
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
