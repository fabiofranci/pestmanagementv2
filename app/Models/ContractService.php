<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
