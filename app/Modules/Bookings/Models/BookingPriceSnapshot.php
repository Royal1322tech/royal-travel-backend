<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingPriceSnapshot extends RoyalModel
{
    protected $table = 'rt_booking_price_snapshots';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Booking::class, 'booking_id', 'id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'currency', 'code');
    }

    public function pricing_policy(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\PricingPolicy::class, 'pricing_policy_id', 'id');
    }

    public function approved_by(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by', 'id');
    }

    public function booking_fx_snapshots(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\BookingFxSnapshot::class, 'price_snapshot_id', 'id');
    }
}
