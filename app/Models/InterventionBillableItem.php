<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterventionBillableItem extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'scheduled_intervention_id',
        'contract_id',
        'billable_item_id',
        'contract_billing_schedule_id',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'status',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (InterventionBillableItem $item): void {
            if (blank($item->contract_id) && filled($item->scheduled_intervention_id)) {
                $item->contract_id = ScheduledIntervention::query()
                    ->whereKey($item->scheduled_intervention_id)
                    ->value('contract_id');
            }

            if (blank($item->total_price) && filled($item->quantity) && filled($item->unit_price)) {
                $item->total_price = round(((float) $item->quantity) * ((float) $item->unit_price), 2);
            }
        });
    }

    public function scheduledIntervention(): BelongsTo
    {
        return $this->belongsTo(ScheduledIntervention::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function billableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class);
    }

    public function contractBillingSchedule(): BelongsTo
    {
        return $this->belongsTo(ContractBillingSchedule::class);
    }
}
