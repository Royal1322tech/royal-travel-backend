<?php

namespace App\Modules\Inventory\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends RoyalModel
{
    protected $table = 'rt_vehicles';
    public $timestamps = false;

    public function provider(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\TransportProvider::class, 'provider_id', 'id');
    }

    public function transport_assignments(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\TransportAssignment::class, 'vehicle_id', 'id');
    }
}
