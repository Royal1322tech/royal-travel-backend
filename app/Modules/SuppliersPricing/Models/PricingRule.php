<?php

namespace App\Modules\SuppliersPricing\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingRule extends RoyalModel
{
    protected $table = 'rt_pricing_rules';
    public $timestamps = false;


    public function policy(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\PricingPolicy::class, 'policy_id', 'id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Supplier::class, 'supplier_id', 'id');
    }
}
