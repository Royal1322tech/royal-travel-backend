<?php

namespace App\Models;

use App\Modules\Identity\Models\Company;
use App\Modules\Identity\Models\CompanyMembership;
use App\Modules\Identity\Models\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Company memberships for this user.
     */
    public function companyMemberships(): HasMany
    {
        return $this->hasMany(
            CompanyMembership::class,
            'user_id',
            'id'
        );
    }

    /**
     * Companies associated with this user.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(
            Company::class,
            (new CompanyMembership())->getTable(),
            'user_id',
            'company_id'
        );
    }

    /**
     * Roles assigned to this user.
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(
            UserRole::class,
            'user_id',
            'id'
        );
    }
}