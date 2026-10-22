<?php

namespace App\Modules\Identity\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyDocument extends RoyalModel
{
    protected $table = 'rt_company_documents';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\Company::class, 'company_id', 'id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\File::class, 'file_id', 'id');
    }

    public function reviewed_by(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by', 'id');
    }
}
