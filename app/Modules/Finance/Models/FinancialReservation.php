<?php

namespace App\Modules\Finance\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialReservation extends RoyalModel
{
    protected $table = 'rt_financial_reservations';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Booking::class, 'booking_id', 'id');
    }

    public function credit_account(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Finance\Models\CreditAccount::class, 'credit_account_id', 'id');
    }

    public function balance_account(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Finance\Models\BalanceAccount::class, 'balance_account_id', 'id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'currency', 'code');
    }

    public function financial_movements(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\FinancialMovement::class, 'reservation_id', 'id');
    }
}
