<?php

namespace App\Modules\Identity\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class File extends RoyalModel
{
    protected $table = 'rt_files';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Company::class, 'company_id', 'id');
    }

    public function uploaded_by(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by', 'id');
    }

    public function company_documents(): HasMany
    {
        return $this->hasMany(\App\Modules\Identity\Models\CompanyDocument::class, 'file_id', 'id');
    }

    public function supplier_entitlements(): HasMany
    {
        return $this->hasMany(\App\Modules\SuppliersPricing\Models\SupplierEntitlement::class, 'evidence_file_id', 'id');
    }
}
