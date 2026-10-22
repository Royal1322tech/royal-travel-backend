<?php

namespace App\Modules\Operations\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupRequest extends RoyalModel
{
    protected $table = 'rt_group_requests';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Company::class, 'company_id', 'id');
    }

    public function requested_by(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by', 'id');
    }

    public function group_offers(): HasMany
    {
        return $this->hasMany(\App\Modules\Operations\Models\GroupOffer::class, 'group_request_id', 'id');
    }
}
