<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use App\Support\Contracts\ContractTotalsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contract extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'customer_site_id',
        'renewed_from_contract_id',
        'contract_number',
        'status',
        'start_date',
        'end_date',
        'renewal',
        'tacit_renewal',
        'renewal_price_increase_percentage',
        'renewal_notice_days',
        'term',
        'payment_terms',
        'billing_frequency',
        'billing_installments_count',
        'total_value',
        'currency',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'tacit_renewal' => 'boolean',
        'renewal_price_increase_percentage' => 'decimal:2',
        'renewal_notice_days' => 'integer',
        'billing_installments_count' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(CustomerSite::class, 'customer_site_id');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_contract_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'renewed_from_contract_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(ContractService::class);
    }

    public function service(): HasOne
    {
        return $this->hasOne(ContractService::class);
    }

    public function scheduledInterventions(): HasMany
    {
        return $this->hasMany(ScheduledIntervention::class);
    }

    public function billingSchedules(): HasMany
    {
        return $this->hasMany(ContractBillingSchedule::class);
    }

    public function contractBillableItems(): HasMany
    {
        return $this->hasMany(ContractBillableItem::class);
    }

    public function servicesTotal(): float
    {
        return app(ContractTotalsService::class)->calculateServicesTotal($this);
    }

    public function billableItemsTotal(): float
    {
        return app(ContractTotalsService::class)->calculateBillableItemsTotal($this);
    }

    public function calculatedTotal(): float
    {
        return app(ContractTotalsService::class)->calculateContractTotal($this);
    }

    public function interventionBillableItems(): HasMany
    {
        return $this->hasMany(InterventionBillableItem::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContractEvent::class);
    }
}
