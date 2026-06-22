<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    public const CONTRACT_SERVICE_MODE_SINGLE = 'single_service';

    public const CONTRACT_SERVICE_MODE_MULTIPLE = 'multiple_services';

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'contract_service_mode',
        'enabled_modules',
        'module_order',
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
            'enabled_modules' => 'array',
            'module_order' => 'array',
        ];
    }

    public static function contractServiceModeOptions(): array
    {
        return [
            self::CONTRACT_SERVICE_MODE_MULTIPLE => 'Piu servizi per contratto',
            self::CONTRACT_SERVICE_MODE_SINGLE => 'Un solo servizio per contratto',
        ];
    }

    public function contractServiceMode(): string
    {
        return array_key_exists($this->contract_service_mode, self::contractServiceModeOptions())
            ? $this->contract_service_mode
            : self::CONTRACT_SERVICE_MODE_MULTIPLE;
    }

    public function usesSingleContractServiceMode(): bool
    {
        return $this->contractServiceMode() === self::CONTRACT_SERVICE_MODE_SINGLE;
    }

    public function allowsMultipleContractServices(): bool
    {
        return $this->contractServiceMode() === self::CONTRACT_SERVICE_MODE_MULTIPLE;
    }

    public function hasModuleEnabled(string $module): bool
    {
        $enabledModules = $this->enabled_modules;

        if (! is_array($enabledModules) || $enabledModules === []) {
            return true;
        }

        return in_array($module, $enabledModules, true);
    }

    public function getModuleSort(string $module): ?int
    {
        $moduleOrder = $this->module_order;

        if (! is_array($moduleOrder) || $moduleOrder === []) {
            return null;
        }

        $position = array_search($module, array_values($moduleOrder), true);

        return $position === false ? null : (($position + 1) * 10);
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
            ->whereNull('customer_id')
            ->oldestOfMany();
    }
}
