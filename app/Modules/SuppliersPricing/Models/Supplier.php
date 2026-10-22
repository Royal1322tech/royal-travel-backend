<?php

namespace App\Modules\SuppliersPricing\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends RoyalModel
{
    protected $table = 'rt_suppliers';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function supplier_entitlements(): HasMany
    {
        return $this->hasMany(\App\Modules\SuppliersPricing\Models\SupplierEntitlement::class, 'supplier_id', 'id');
    }

    public function supplier_capabilities(): HasMany
    {
        return $this->hasMany(\App\Modules\SuppliersPricing\Models\SupplierCapability::class, 'supplier_id', 'id');
    }

    public function pricing_rules(): HasMany
    {
        return $this->hasMany(\App\Modules\SuppliersPricing\Models\PricingRule::class, 'supplier_id', 'id');
    }

    public function supplier_hotels(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\SupplierHotel::class, 'supplier_id', 'id');
    }

    public function transport_providers(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\TransportProvider::class, 'supplier_id', 'id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\Booking::class, 'supplier_id', 'id');
    }

    public function supplier_booking_attempts(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\SupplierBookingAttempt::class, 'supplier_id', 'id');
    }

    public function supplier_external_refs(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\SupplierExternalReference::class, 'supplier_id', 'id');
    }

    public function supplier_settlements(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\SupplierSettlement::class, 'supplier_id', 'id');
    }
}
