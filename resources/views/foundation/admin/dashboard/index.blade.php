@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="royal-dashboard space-y-7">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><div class="royal-eyebrow">OVERVIEW / PERFORMANCE</div><h1 class="royal-page-title">Dashboard Overview</h1><p class="royal-page-subtitle">Your hotel operations at a glance, using live platform data.</p></div>
        <div class="royal-date-pill">{{ now()->format('d M Y') }}</div>
    </div>
    @php
        $stats = [
            ['label'=>'Total Bookings','value'=>$overview['totalBookings'],'icon'=>'▤','tone'=>'blue'],
            ['label'=>'Today Bookings','value'=>$overview['todayBookings'],'icon'=>'◷','tone'=>'green'],
            ['label'=>'Pending Bookings','value'=>$overview['pendingBookings'],'icon'=>'◌','tone'=>'amber'],
            ['label'=>'Confirmed Bookings','value'=>$overview['confirmedBookings'],'icon'=>'✓','tone'=>'cyan'],
            ['label'=>'Total Orders','value'=>$overview['totalOrders'],'icon'=>'▣','tone'=>'blue'],
            ['label'=>'Registered Companies','value'=>$overview['totalCompanies'],'icon'=>'▥','tone'=>'green'],
            ['label'=>'Pending Refunds','value'=>$overview['pendingRefunds'],'icon'=>'↩','tone'=>'amber'],
        ];
    @endphp
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($stats as $stat)
            <article class="royal-stat-card">
                <div class="flex items-start justify-between gap-3"><div class="royal-stat-label">{{ $stat['label'] }}</div><span class="royal-stat-icon royal-tone-{{ $stat['tone'] }}">{{ $stat['icon'] }}</span></div>
                <div class="royal-stat-value">{{ number_format($stat['value']) }}</div>
                <div class="royal-stat-footer"><span class="royal-stat-dot"></span> Live platform total</div>
                <div class="royal-stat-spark" aria-hidden="true"><svg viewBox="0 0 140 30" preserveAspectRatio="none"><path d="M0 24 L13 20 L25 23 L38 11 L52 16 L66 8 L78 18 L91 13 L103 20 L118 9 L140 14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
            </article>
        @endforeach
    </div>
    <section class="royal-panel overflow-hidden">
        <div class="royal-panel-heading flex flex-wrap items-center justify-between gap-3"><div><h2 class="royal-panel-title">Recent Bookings</h2><p class="royal-panel-subtitle">Latest hotel bookings across B2B and B2C channels</p></div><span class="royal-section-pill">LATEST ACTIVITY</span></div>
        <div class="overflow-x-auto"><table class="royal-data-table w-full text-left text-sm"><thead><tr><th>Booking ID</th><th>Service</th><th>Channel</th><th>Status</th><th>Approved Total</th><th>Date</th></tr></thead>
        <tbody>
        @forelse($overview['recentBookings'] as $booking)
            <tr><td class="font-semibold text-[#102654]">{{ $booking->booking_no }}</td><td class="capitalize">{{ $booking->service_type }}</td><td class="uppercase">{{ $booking->channel }}</td><td><span class="royal-status-pill">{{ str_replace('_',' ',ucfirst($booking->status)) }}</span></td><td class="font-semibold">@if($booking->approved_total !== null){{ number_format((float)$booking->approved_total,2) }} {{ $booking->currency }}@else — @endif</td><td>{{ \Illuminate\Support\Carbon::parse($booking->created_at)->format('d M Y') }}</td></tr>
        @empty
            <tr><td colspan="6" class="py-12 text-center text-slate-500">No bookings available yet.</td></tr>
        @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
