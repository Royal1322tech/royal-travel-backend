<?php

namespace App\Modules\Operations\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupOffer extends RoyalModel
{
    protected $table = 'rt_group_offers';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'valid_until' => 'datetime',
        ];
    }

    public function group_request(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Operations\Models\GroupRequest::class, 'group_request_id', 'id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'currency', 'code');
    }
}
