<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSite extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'auto_created_from_customer',
        'name',
        'address',
        'city',
        'postcode',
        'province',
        'country',
        'contact_name',
        'contact_phone',
        'contact_email',
        'site_code',
        'notes',
        'status',
    ];

    protected $casts = [
        'auto_created_from_customer' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function areas()
    {
        return $this->hasMany(Area::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
