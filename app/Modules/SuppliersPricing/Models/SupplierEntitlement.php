<?php

namespace App\Modules\SuppliersPricing\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierEntitlement extends RoyalModel
{
    protected $table = 'rt_supplier_entitlements';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_allowed' => 'boolean',
            'is_verified' => 'boolean',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SuppliersPricing\Models\Supplier::class, 'supplier_id', 'id');
    }

    public function evidence_file(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\File::class, 'evidence_file_id', 'id');
    }

    public function approved_by(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by', 'id');
    }
}
