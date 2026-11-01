<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationCase extends RoyalModel
{
    protected $table = 'rt_reconciliation_cases';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Booking::class, 'booking_id', 'id');
    }

    public function supplier_attempt(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\SupplierBookingAttempt::class, 'supplier_attempt_id', 'id');
    }

    public function assigned_to(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to', 'id');
    }
}
