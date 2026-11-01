<?php

namespace App\Modules\Admin\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminBookingService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = DB::table('rt_bookings');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['service_type'])) {
            $query->where('service_type', $filters['service_type']);
        }

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
    }
}