<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ContractService extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'service_type_id',
        'customer_site_id',
        'area_id',
        'description',
        'frequency',
        'operational_frequency',
        'billing_frequency',
        'quantity',
        'unit_price',
        'total_price',
        'currency',
        'starts_on',
        'ends_on',
        'status',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (ContractService $service): void {
            $service->ensureContractServiceModeAllowsCreation();
        });
    }

    protected function ensureContractServiceModeAllowsCreation(): void
    {
        $tenant = $this->resolveTenantForServiceMode();

        if (! $tenant?->usesSingleContractServiceMode()) {
            return;
        }

        if (blank($this->contract_id)) {
            return;
        }

        $query = static::query()
            ->where('contract_id', $this->contract_id);

        if (filled($this->tenant_id)) {
            $query->where('tenant_id', $this->tenant_id);
        }

        if (! $query->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'contract_id' => 'Questo tenant consente un solo servizio per contratto.',
        ]);
    }

    protected function resolveTenantForServiceMode(): ?Tenant
    {
        $currentTenant = app(CurrentTenant::class)->get();

        if ($currentTenant && ((int) $currentTenant->getKey() === (int) ($this->tenant_id ?: $currentTenant->getKey()))) {
            return $currentTenant;
        }

        if (filled($this->tenant_id)) {
            return Tenant::query()->find($this->tenant_id);
        }

        return $currentTenant;
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(CustomerSite::class, 'customer_site_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function scheduledInterventions(): HasMany
    {
        return $this->hasMany(ScheduledIntervention::class);
    }
}
