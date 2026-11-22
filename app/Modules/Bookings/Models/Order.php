<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends RoyalModel
{
    protected $table = 'rt_orders';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Company::class, 'company_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Customer::class, 'customer_id', 'id');
    }

    public function customer_user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'customer_user_id', 'id');
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'currency', 'code');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\Booking::class, 'order_id', 'id');
    }

    public function payment_intents(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\PaymentIntent::class, 'order_id', 'id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\Invoice::class, 'order_id', 'id');
    }
}
