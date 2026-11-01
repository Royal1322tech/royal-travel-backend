<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierExternalReference extends RoyalModel
{
    protected $table = 'rt_supplier_external_refs';
    public $timestamps = false;

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\SupplierBookingAttempt::class, 'attempt_id', 'id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Supplier::class, 'supplier_id', 'id');
    }
}
