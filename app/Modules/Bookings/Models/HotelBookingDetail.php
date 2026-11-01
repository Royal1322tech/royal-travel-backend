<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelBookingDetail extends RoyalModel
{
    protected $table = 'rt_hotel_booking_details';
    protected $primaryKey = 'booking_id';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Booking::class, 'booking_id', 'id');
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Hotel::class, 'hotel_id', 'id');
    }
}
