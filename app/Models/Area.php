<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'customer_site_id',
        'service_type_id',
        'name',
        'description',
        'thresholds',
        'status',
    ];

    protected $casts = [
        'thresholds' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function site()
    {
        return $this->belongsTo(CustomerSite::class, 'customer_site_id');
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function monitoringPoints()
    {
        return $this->hasMany(MonitoringPoint::class);
    }
}
