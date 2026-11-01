<?php

namespace App\Modules\Finance\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentAllocation extends RoyalModel
{
    protected $table = 'rt_payment_allocations';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function payment_intent(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Finance\Models\PaymentIntent::class, 'payment_intent_id', 'id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Booking::class, 'booking_id', 'id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'currency', 'code');
    }
}
