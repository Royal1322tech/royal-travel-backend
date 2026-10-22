<?php

namespace App\Modules\Finance\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentIntent extends RoyalModel
{
    protected $table = 'rt_payment_intents';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Order::class, 'order_id', 'id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'currency', 'code');
    }

    public function idempotency_key(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\IdempotencyKey::class, 'idempotency_key_id', 'id');
    }

    public function payment_allocations(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\PaymentAllocation::class, 'payment_intent_id', 'id');
    }

    public function provider_transactions(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\ProviderTransaction::class, 'payment_intent_id', 'id');
    }

    public function financial_movements(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\FinancialMovement::class, 'payment_intent_id', 'id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\Refund::class, 'payment_intent_id', 'id');
    }
}
