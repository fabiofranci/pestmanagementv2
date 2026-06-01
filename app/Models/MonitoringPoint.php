<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonitoringPoint extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'area_id',
        'code',
        'name',
        'service_type_id',
        'type',
        'model',
        'product',
        'latitude',
        'longitude',
        'map_position',
        'status',
    ];

    protected $casts = [
        'map_position' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }
}
