<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledIntervention extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'contract_service_id',
        'customer_site_id',
        'service_type_id',
        'planned_date',
        'planned_time',
        'status',
        'notes',
    ];

    protected $casts = [
        'planned_date' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function contractService(): BelongsTo
    {
        return $this->belongsTo(ContractService::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(CustomerSite::class, 'customer_site_id');
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function interventionBillableItems(): HasMany
    {
        return $this->hasMany(InterventionBillableItem::class);
    }
}
