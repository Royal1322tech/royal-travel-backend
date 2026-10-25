<?php

namespace App\Modules\Admin\Services;

use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function getOverview(): array
    {
        $bookings = DB::table('rt_bookings');

        $totalBookings = (clone $bookings)->count();

        $todayBookings = (clone $bookings)
            ->whereDate('created_at', today())
            ->count();

        $pendingBookings = (clone $bookings)
            ->whereIn('status', [
                'draft',
                'pending',
                'pending_approval',
            ])
            ->count();

        $confirmedBookings = (clone $bookings)
            ->where('status', 'confirmed')
            ->count();

        $totalOrders = DB::table('rt_orders')->count();

        $activeCompanies = DB::table('rt_companies')
            ->count();

        $pendingRefunds = DB::table('rt_refunds')
            ->where('status', 'requested')
            ->count();

        $recentBookings = DB::table('rt_bookings')
            ->select([
                'booking_no',
                'service_type',
                'channel',
                'status',
                'currency',
                'approved_total',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return [
            'totalBookings' => $totalBookings,
            'todayBookings' => $todayBookings,
            'pendingBookings' => $pendingBookings,
            'confirmedBookings' => $confirmedBookings,
            'totalOrders' => $totalOrders,
            'totalCompanies' => $activeCompanies,
            'pendingRefunds' => $pendingRefunds,
            'recentBookings' => $recentBookings,
        ];
    }
}