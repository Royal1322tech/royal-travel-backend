<?php

namespace App\Modules\Bookings\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutboxEvent extends RoyalModel
{
    protected $table = 'rt_outbox_events';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
