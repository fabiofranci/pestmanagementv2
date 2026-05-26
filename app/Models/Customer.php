<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'legal_name',
        'tax_id',
        'email',
        'phone',
        'address',
        'city',
        'postcode',
        'province',
        'country',
        'notes',
        'status',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sites()
    {
        return $this->hasMany(CustomerSite::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }
}
