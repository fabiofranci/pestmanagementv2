<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
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
}
