<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadSource extends Model
{
    protected $fillable = [
        'name',
        'type',
        'base_url',
        'active',
        'config',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
        'config' => 'array',
    ];
    //
}
