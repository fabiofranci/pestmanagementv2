<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFetchRun extends Model
{
    protected $fillable = [
        'lead_source_id',
        'query',
        'region',
        'province',
        'sector',
        'found_count',
        'created_count',
        'updated_count',
        'error_count',
        'status',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }
    //
}
