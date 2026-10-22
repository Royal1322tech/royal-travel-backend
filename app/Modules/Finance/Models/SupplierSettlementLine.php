<?php

namespace App\Modules\Finance\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierSettlementLine extends RoyalModel
{
    protected $table = 'rt_supplier_settlement_lines';
    public $timestamps = false;


    public function settlement(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Finance\Models\SupplierSettlement::class, 'settlement_id', 'id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Booking::class, 'booking_id', 'id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'currency', 'code');
    }
}
