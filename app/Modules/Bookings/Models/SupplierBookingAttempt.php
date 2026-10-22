<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierBookingAttempt extends RoyalModel
{
    protected $table = 'rt_supplier_booking_attempts';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Booking::class, 'booking_id', 'id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Supplier::class, 'supplier_id', 'id');
    }

    public function supplier_external_refs(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\SupplierExternalReference::class, 'attempt_id', 'id');
    }

    public function reconciliation_cases(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\ReconciliationCase::class, 'supplier_attempt_id', 'id');
    }
}
