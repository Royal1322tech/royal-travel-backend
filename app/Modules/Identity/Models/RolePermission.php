<?php

namespace App\Modules\Identity\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RolePermission extends RoyalModel
{
    protected $table = 'rt_role_permissions';
    // Composite primary key: Eloquent does not natively support composite model keys.
    // Use query builder for updates/deletes on this table.
    public $incrementing = false;
    public $timestamps = false;

    public function role(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Role::class, 'role_id', 'id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Permission::class, 'permission_id', 'id');
    }
}
