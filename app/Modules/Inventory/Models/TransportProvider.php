<?php

namespace App\Modules\Inventory\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportProvider extends RoyalModel
{
    protected $table = 'rt_transport_providers';
    public $timestamps = false;

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Supplier::class, 'supplier_id', 'id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\Vehicle::class, 'provider_id', 'id');
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\Driver::class, 'provider_id', 'id');
    }

    public function transport_booking_details(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\TransportBookingDetail::class, 'provider_id', 'id');
    }
}
