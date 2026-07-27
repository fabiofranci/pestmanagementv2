<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Customer extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'customer_group_id',
        'default_site_same_as_customer',
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

        static::saved(function (Customer $customer): void {
            $customer->syncDefaultSiteFromCustomerData();
        });
    }

    protected function casts(): array
    {
        return [
            'default_site_same_as_customer' => 'boolean',
        ];
    }

    public function getDisplayNameAttribute(): string
    {
        return filled($this->legal_name) ? $this->legal_name : $this->name;
    }

    public function shouldUseCustomerDataAsDefaultSite(): bool
    {
        return (bool) $this->default_site_same_as_customer;
    }

    public function syncDefaultSiteFromCustomerData(): void
    {
        if (! $this->shouldUseCustomerDataAsDefaultSite() || ! $this->canSyncDefaultSite()) {
            return;
        }

        $site = $this->sites()
            ->where('auto_created_from_customer', true)
            ->first();

        if (! $site && $this->sites()->exists()) {
            return;
        }

        $site ??= new CustomerSite([
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->getKey(),
            'auto_created_from_customer' => true,
        ]);

        $site->fill($this->defaultSiteAttributesFromCustomer());
        $site->save();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultSiteAttributesFromCustomer(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->getKey(),
            'auto_created_from_customer' => true,
            'name' => filled($this->display_name) ? $this->display_name : 'Sede principale',
            'address' => $this->address,
            'city' => $this->city,
            'postcode' => $this->postcode,
            'province' => $this->province,
            'country' => $this->country,
            'contact_name' => $this->name,
            'contact_phone' => $this->phone ?: $this->mobile ?: $this->secondary_phone,
            'contact_email' => $this->email,
            'notes' => 'Sede creata automaticamente dai dati cliente.',
            'status' => 'active',
        ];
    }

    protected function canSyncDefaultSite(): bool
    {
        $connection = $this->getConnectionName();

        return Schema::connection($connection)->hasColumn('customer_sites', 'auto_created_from_customer');
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

    public function billableItemPrices(): HasMany
    {
        return $this->hasMany(CustomerBillableItemPrice::class);
    }
}
