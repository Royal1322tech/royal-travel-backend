<?php

namespace App\Modules\Identity\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserRole extends RoyalModel
{
    protected $table = 'rt_user_roles';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Role::class, 'role_id', 'id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Company::class, 'company_id', 'id');
    }
}
