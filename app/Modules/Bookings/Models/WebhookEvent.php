<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEvent extends RoyalModel
{
    protected $table = 'rt_webhook_events';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'signature_verified' => 'boolean',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
