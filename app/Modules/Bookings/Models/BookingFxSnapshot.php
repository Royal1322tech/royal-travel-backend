<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingFxSnapshot extends RoyalModel
{
    protected $table = 'rt_booking_fx_snapshots';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function price_snapshot(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\BookingPriceSnapshot::class, 'price_snapshot_id', 'id');
    }

    public function fx_rate(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\FxRate::class, 'fx_rate_id', 'id');
    }

    public function base_currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'base_currency', 'code');
    }

    public function quote_currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'quote_currency', 'code');
    }
}
