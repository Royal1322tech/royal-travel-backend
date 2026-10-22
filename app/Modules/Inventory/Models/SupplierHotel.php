<?php

namespace App\Modules\Inventory\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierHotel extends RoyalModel
{
    protected $table = 'rt_supplier_hotels';
    public $timestamps = false;

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Supplier::class, 'supplier_id', 'id');
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Hotel::class, 'hotel_id', 'id');
    }
}
