<?php

namespace App\Modules\Operations\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpecialRequest extends RoyalModel
{
    protected $table = 'rt_special_requests';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Booking::class, 'booking_id', 'id');
    }
}
