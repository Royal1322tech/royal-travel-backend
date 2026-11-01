<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportBookingDetail extends RoyalModel
{
    protected $table = 'rt_transport_booking_details';
    protected $primaryKey = 'booking_id';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'pickup_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Booking::class, 'booking_id', 'id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\TransportProvider::class, 'provider_id', 'id');
    }

    public function transport_assignments(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\TransportAssignment::class, 'booking_id', 'booking_id');
    }
}
