<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'status',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function customerSites()
    {
        return $this->hasMany(CustomerSite::class);
    }

    public function serviceTypes()
    {
        return $this->hasMany(ServiceType::class);
    }

    public function pestTypes()
    {
        return $this->hasMany(PestType::class);
    }

    public function areas()
    {
        return $this->hasMany(Area::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
