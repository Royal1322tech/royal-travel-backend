<?php

namespace App\Modules\Finance\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends RoyalModel
{
    protected $table = 'rt_refunds';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Booking::class, 'booking_id', 'id');
    }

    public function payment_intent(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Finance\Models\PaymentIntent::class, 'payment_intent_id', 'id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'currency', 'code');
    }

    public function requested_by(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by', 'id');
    }
}
