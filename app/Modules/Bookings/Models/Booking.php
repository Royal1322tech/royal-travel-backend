<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends RoyalModel
{
    protected $table = 'rt_bookings';

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Order::class, 'order_id', 'id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Company::class, 'company_id', 'id');
    }

    public function customer_user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'customer_user_id', 'id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Supplier::class, 'supplier_id', 'id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'currency', 'code');
    }

    public function booking_guests(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\BookingGuest::class, 'booking_id', 'id');
    }

    public function hotel_booking_details(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\HotelBookingDetail::class, 'booking_id', 'id');
    }

    public function transport_booking_details(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\TransportBookingDetail::class, 'booking_id', 'id');
    }

    public function service_booking_details(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\ServiceBookingDetail::class, 'booking_id', 'id');
    }

    public function booking_price_snapshots(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\BookingPriceSnapshot::class, 'booking_id', 'id');
    }

    public function supplier_booking_attempts(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\SupplierBookingAttempt::class, 'booking_id', 'id');
    }

    public function reconciliation_cases(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\ReconciliationCase::class, 'booking_id', 'id');
    }

    public function payment_allocations(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\PaymentAllocation::class, 'booking_id', 'id');
    }

    public function financial_reservations(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\FinancialReservation::class, 'booking_id', 'id');
    }

    public function financial_movements(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\FinancialMovement::class, 'booking_id', 'id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\Refund::class, 'booking_id', 'id');
    }

    public function invoice_lines(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\InvoiceLine::class, 'booking_id', 'id');
    }

    public function supplier_settlement_lines(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\SupplierSettlementLine::class, 'booking_id', 'id');
    }

    public function cancellation_requests(): HasMany
    {
        return $this->hasMany(\App\Modules\Operations\Models\CancellationRequest::class, 'booking_id', 'id');
    }

    public function special_requests(): HasMany
    {
        return $this->hasMany(\App\Modules\Operations\Models\SpecialRequest::class, 'booking_id', 'id');
    }
}
