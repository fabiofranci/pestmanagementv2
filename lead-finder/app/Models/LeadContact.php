<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Lead;

class LeadContact extends Model
{
    protected $fillable = [
        'lead_id',
        'type',
        'value',
        'label',
        'source_url',
        'is_primary',
        'is_valid',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_valid' => 'boolean',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
    //
}
