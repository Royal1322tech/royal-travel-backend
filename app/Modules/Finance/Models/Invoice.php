<?php

namespace App\Modules\Finance\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends RoyalModel
{
    protected $table = 'rt_invoices';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Company::class, 'company_id', 'id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Bookings\Models\Order::class, 'order_id', 'id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Currency::class, 'currency', 'code');
    }

    public function invoice_lines(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\InvoiceLine::class, 'invoice_id', 'id');
    }
}
