<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractBillableItem extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'billable_item_id',
        'quantity',
        'unit_price',
        'discount_percentage',
        'total_price',
        'notes',
        'status',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (ContractBillableItem $item): void {
            if (blank($item->total_price) && filled($item->quantity) && filled($item->unit_price)) {
                $item->total_price = $item->calculateTotalPrice();
            }
        });
    }

    public function calculateTotalPrice(): float
    {
        $quantity = blank($this->quantity) ? 0 : (float) $this->quantity;
        $unitPrice = blank($this->unit_price) ? 0 : (float) $this->unit_price;
        $discountPercentage = blank($this->discount_percentage) ? 0 : (float) $this->discount_percentage;
        $baseTotal = $quantity * $unitPrice;

        return round($baseTotal - ($baseTotal * ($discountPercentage / 100)), 2);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function billableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class);
    }
}
