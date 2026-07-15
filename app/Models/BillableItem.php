<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillableItem extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'default_unit_price',
        'vat_rate',
        'status',
    ];

    protected $casts = [
        'default_unit_price' => 'decimal:2',
        'vat_rate' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customerPrices(): HasMany
    {
        return $this->hasMany(CustomerBillableItemPrice::class);
    }

    public function contractBillableItems(): HasMany
    {
        return $this->hasMany(ContractBillableItem::class);
    }

    public function interventionBillableItems(): HasMany
    {
        return $this->hasMany(InterventionBillableItem::class);
    }
}
