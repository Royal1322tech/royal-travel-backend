<?php

namespace App\Modules\SuppliersPricing\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FxRate extends RoyalModel
{
    protected $table = 'rt_fx_rates';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
            'recorded_at' => 'datetime',
        ];
    }

    public function base_currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'base_currency', 'code');
    }

    public function quote_currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'quote_currency', 'code');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Company::class, 'company_id', 'id');
    }

    public function booking_fx_snapshots(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\BookingFxSnapshot::class, 'fx_rate_id', 'id');
    }
}
