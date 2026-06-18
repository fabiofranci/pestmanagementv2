<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'documentable_type',
        'documentable_id',
        'type',
        'title',
        'file_path',
        'mime_type',
        'size',
        'visible_to_customer',
        'generated_at',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'visible_to_customer' => 'boolean',
        'generated_at' => 'datetime',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
