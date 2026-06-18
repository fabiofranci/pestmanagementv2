<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'tenant_id', 'customer_id', 'is_superuser'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_superuser' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by_user_id');
    }

    public function isSuperuser(): bool
    {
        return (bool) $this->is_superuser;
    }

    public function isTenantAdmin(): bool
    {
        return ! $this->isSuperuser()
            && filled($this->tenant_id)
            && blank($this->customer_id);
    }

    public function isTenantCustomer(): bool
    {
        return ! $this->isSuperuser()
            && filled($this->tenant_id)
            && filled($this->customer_id);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isSuperuser()
            || $this->isTenantAdmin()
            || $this->isTenantCustomer();
    }
}
