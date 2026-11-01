<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdempotencyKey extends RoyalModel
{
    protected $table = 'rt_idempotency_keys';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function actor_user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'actor_user_id', 'id');
    }

    public function payment_intents(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\PaymentIntent::class, 'idempotency_key_id', 'id');
    }
}
