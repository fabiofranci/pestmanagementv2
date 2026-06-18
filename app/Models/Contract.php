<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contract extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'customer_site_id',
        'contract_number',
        'status',
        'start_date',
        'end_date',
        'renewal',
        'term',
        'payment_terms',
        'total_value',
        'currency',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
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

    public function services(): HasMany
    {
        return $this->hasMany(ContractService::class);
    }

    public function scheduledInterventions(): HasMany
    {
        return $this->hasMany(ScheduledIntervention::class);
    }

    public function billingSchedules(): HasMany
    {
        return $this->hasMany(ContractBillingSchedule::class);
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
