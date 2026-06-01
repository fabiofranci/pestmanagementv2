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
        'db_host',
        'db_port',
        'db_database',
        'db_username',
        'db_password',
        'panel_palette',
        'panel_theme_mode',
        'panel_font_family',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'db_password' => 'encrypted',
        ];
    }

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

    public function tenantAdmin()
    {
        return $this->hasOne(User::class)
            ->where('is_superuser', false)
            ->oldestOfMany();
    }
}
