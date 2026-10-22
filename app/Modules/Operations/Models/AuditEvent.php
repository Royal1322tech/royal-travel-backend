<?php

namespace App\Modules\Operations\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditEvent extends RoyalModel
{
    protected $table = 'rt_audit_events';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function actor_user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'actor_user_id', 'id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Company::class, 'company_id', 'id');
    }
}
