<?php

namespace App\Modules\Identity\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends RoyalModel
{
    protected $table = 'rt_roles';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function role_permissions(): HasMany
    {
        return $this->hasMany(\App\Modules\Identity\Models\RolePermission::class, 'role_id', 'id');
    }

    public function user_roles(): HasMany
    {
        return $this->hasMany(\App\Modules\Identity\Models\UserRole::class, 'role_id', 'id');
    }
}
