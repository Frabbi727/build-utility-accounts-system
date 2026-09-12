<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable;

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
        ];
    }

    /** @return HasOne<Owner, $this> */
    public function owner(): HasOne
    {
        return $this->hasOne(Owner::class);
    }

    /** @return HasOne<Tenant, $this> */
    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class);
    }

    public function isResident(): bool
    {
        return $this->hasAnyRole(Role::residents());
    }

    public function isOwner(): bool
    {
        return $this->hasRole(Role::Owner->value);
    }

    public function isTenant(): bool
    {
        return $this->hasRole(Role::Tenant->value);
    }

    /**
     * Flats this user may see. Staff roles see everything; an owner sees only their own.
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRole(Role::staff());
    }

    public function canManageMoney(): bool
    {
        return $this->hasAnyRole(Role::moneyHandlers());
    }

    /**
     * Administrators alone manage users, roles, the chart of accounts and periods.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin->value);
    }

    /** @return HasMany<UserDevice, $this> */
    public function userDevices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
