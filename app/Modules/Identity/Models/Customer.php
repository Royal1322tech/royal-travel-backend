<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Customer extends RoyalModel
{
    protected $table = 'rt_customers';
    protected $fillable = ['company_id', 'first_name', 'last_name', 'email', 'phone', 'created_by'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class, 'company_id'); }
}
