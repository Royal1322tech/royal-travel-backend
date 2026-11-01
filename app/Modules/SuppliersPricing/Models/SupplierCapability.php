<?php

namespace App\Modules\SuppliersPricing\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierCapability extends RoyalModel
{
    protected $table = 'rt_supplier_capabilities';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Supplier::class, 'supplier_id', 'id');
    }
}
