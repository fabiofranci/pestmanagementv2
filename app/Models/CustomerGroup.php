<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerGroup extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'status',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
