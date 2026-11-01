<?php

namespace App\Modules\Identity\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends RoyalModel
{
    protected $table = 'rt_permissions';
    public $timestamps = false;

    public function role_permissions(): HasMany
    {
        return $this->hasMany(\App\Modules\Identity\Models\RolePermission::class, 'permission_id', 'id');
    }
}
