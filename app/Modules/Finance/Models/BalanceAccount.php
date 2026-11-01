<?php

namespace App\Modules\Finance\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BalanceAccount extends RoyalModel
{
    protected $table = 'rt_balance_accounts';
    public $timestamps = false;

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Company::class, 'company_id', 'id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'currency', 'code');
    }

    public function financial_reservations(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\FinancialReservation::class, 'balance_account_id', 'id');
    }
}
