<?php

namespace App\Modules\SuppliersPricing\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends RoyalModel
{
    protected $table = 'rt_currencies';
    protected $primaryKey = 'code';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function fx_rates(): HasMany
    {
        return $this->hasMany(\App\Modules\SuppliersPricing\Models\FxRate::class, 'base_currency', 'code');
    }

    public function fx_ratesRecords(): HasMany
    {
        return $this->hasMany(\App\Modules\SuppliersPricing\Models\FxRate::class, 'quote_currency', 'code');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\Order::class, 'currency', 'code');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\Booking::class, 'currency', 'code');
    }

    public function booking_price_snapshots(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\BookingPriceSnapshot::class, 'currency', 'code');
    }

    public function booking_fx_snapshots(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\BookingFxSnapshot::class, 'base_currency', 'code');
    }

    public function booking_fx_snapshotsRecords(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\BookingFxSnapshot::class, 'quote_currency', 'code');
    }

    public function payment_intents(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\PaymentIntent::class, 'currency', 'code');
    }

    public function payment_allocations(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\PaymentAllocation::class, 'currency', 'code');
    }

    public function provider_transactions(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\ProviderTransaction::class, 'currency', 'code');
    }

    public function credit_accounts(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\CreditAccount::class, 'currency', 'code');
    }

    public function balance_accounts(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\BalanceAccount::class, 'currency', 'code');
    }

    public function financial_reservations(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\FinancialReservation::class, 'currency', 'code');
    }

    public function financial_movements(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\FinancialMovement::class, 'currency', 'code');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\Refund::class, 'currency', 'code');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\Invoice::class, 'currency', 'code');
    }

    public function invoice_lines(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\InvoiceLine::class, 'currency', 'code');
    }

    public function supplier_settlements(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\SupplierSettlement::class, 'currency', 'code');
    }

    public function supplier_settlement_lines(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\SupplierSettlementLine::class, 'currency', 'code');
    }

    public function cancellation_requests(): HasMany
    {
        return $this->hasMany(\App\Modules\Operations\Models\CancellationRequest::class, 'currency', 'code');
    }

    public function group_offers(): HasMany
    {
        return $this->hasMany(\App\Modules\Operations\Models\GroupOffer::class, 'currency', 'code');
    }
}
