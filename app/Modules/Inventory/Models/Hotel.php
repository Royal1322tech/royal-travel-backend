<?php

namespace App\Modules\Inventory\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends RoyalModel
{
    protected $table = 'rt_hotels';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function supplier_hotels(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\SupplierHotel::class, 'hotel_id', 'id');
    }

    public function hotel_booking_details(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\HotelBookingDetail::class, 'hotel_id', 'id');
    }
}
