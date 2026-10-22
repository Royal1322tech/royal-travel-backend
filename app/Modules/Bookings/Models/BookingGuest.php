<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingGuest extends RoyalModel
{
    protected $table = 'rt_booking_guests';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_lead' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Booking::class, 'booking_id', 'id');
    }
}
