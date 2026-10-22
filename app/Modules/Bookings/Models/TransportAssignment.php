<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportAssignment extends RoyalModel
{
    protected $table = 'rt_transport_assignments';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\TransportBookingDetail::class, 'booking_id', 'booking_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Vehicle::class, 'vehicle_id', 'id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Driver::class, 'driver_id', 'id');
    }
}
