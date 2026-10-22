<?php

namespace App\Modules\SuppliersPricing\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingPolicy extends RoyalModel
{
    protected $table = 'rt_pricing_policies';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Company::class, 'company_id', 'id');
    }

    public function pricing_rules(): HasMany
    {
        return $this->hasMany(\App\Modules\SuppliersPricing\Models\PricingRule::class, 'policy_id', 'id');
    }

    public function booking_price_snapshots(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\BookingPriceSnapshot::class, 'pricing_policy_id', 'id');
    }
}
