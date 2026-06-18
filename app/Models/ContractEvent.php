<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractEvent extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'event_type',
        'title',
        'payload',
        'created_by_user_id',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
