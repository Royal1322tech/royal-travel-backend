@extends('admin.layouts.app')
@section('title','Dashboard')
@section('content')
@php
    $stats = [
        ['label'=>'Total Bookings','value'=>$overview['totalBookings'],'symbol'=>'▤','tone'=>'blue'],
        ['label'=>'Today Bookings','value'=>$overview['todayBookings'],'symbol'=>'◷','tone'=>'green'],
        ['label'=>'Pending Bookings','value'=>$overview['pendingBookings'],'symbol'=>'◫','tone'=>'amber'],
        ['label'=>'Confirmed Bookings','value'=>$overview['confirmedBookings'],'symbol'=>'✓','tone'=>'teal'],
        ['label'=>'Total Orders','value'=>$overview['totalOrders'],'symbol'=>'▣','tone'=>'violet'],
        ['label'=>'Registered Companies','value'=>$overview['totalCompanies'],'symbol'=>'▥','tone'=>'blue'],
        ['label'=>'Pending Refunds','value'=>$overview['pendingRefunds'],'symbol'=>'↩','tone'=>'amber'],
    ];
    $bookingTotal = max(1, (int) $overview['totalBookings']);
    $pendingPct = min(100, round(100 * (int) $overview['pendingBookings'] / $bookingTotal));
    $confirmedPct = min(100, round(100 * (int) $overview['confirmedBookings'] / $bookingTotal));
@endphp
<div class="rt-page-heading">
    <div><div class="rt-eyebrow">OVERVIEW / DASHBOARD</div><h1>Dashboard overview</h1><p>A clear view of your Royal Travel operations.</p></div>
    <span class="rt-date-chip">{{ now()->format('l, d M Y') }}</span>
</div>
<div class="rt-stats-grid">
    @foreach($stats as $stat)
        <article class="rt-stat-card">
            <div class="rt-stat-top"><span class="rt-stat-icon tone-{{ $stat['tone'] }}">{{ $stat['symbol'] }}</span><span class="rt-stat-caption">LIVE TOTAL</span></div>
            <div class="rt-stat-value">{{ number_format($stat['value']) }}</div>
            <div class="rt-stat-label">{{ $stat['label'] }}</div>
        </article>
    @endforeach
</div>
<div class="rt-dashboard-panels">
    <section class="rt-surface rt-performance">
        <div class="rt-panel-head"><div><span class="rt-eyebrow">BOOKINGS</span><h2>Booking status</h2><p>Current booking totals, based on live records</p></div><span class="rt-soft-chip">All time</span></div>
        <div class="rt-status-metrics"><div><span>Confirmed</span><strong>{{ number_format($overview['confirmedBookings']) }}</strong></div><div><span>Pending</span><strong>{{ number_format($overview['pendingBookings']) }}</strong></div></div>
        <div class="rt-progress-row"><div><span>Confirmed bookings</span><strong>{{ $confirmedPct }}%</strong></div><div class="rt-progress-track"><div class="rt-progress-fill green" style="width:{{ $confirmedPct }}%"></div></div></div>
        <div class="rt-progress-row"><div><span>Pending bookings</span><strong>{{ $pendingPct }}%</strong></div><div class="rt-progress-track"><div class="rt-progress-fill amber" style="width:{{ $pendingPct }}%"></div></div></div>
        <p class="rt-data-note">Percentages are calculated from total bookings. Other statuses may exist.</p>
    </section>
    <section class="rt-surface rt-quickview">
        <div class="rt-panel-head"><div><span class="rt-eyebrow">PLATFORM</span><h2>Quick overview</h2><p>Operational counters</p></div></div>
        <div class="rt-quick-list"><div><span>Orders</span><strong>{{ number_format($overview['totalOrders']) }}</strong></div><div><span>Registered companies</span><strong>{{ number_format($overview['totalCompanies']) }}</strong></div><div><span>Pending refunds</span><strong>{{ number_format($overview['pendingRefunds']) }}</strong></div></div>
    </section>
</div>
<section class="rt-surface rt-table-panel">
    <div class="rt-panel-head"><div><span class="rt-eyebrow">ACTIVITY</span><h2>Recent bookings</h2><p>Latest bookings across B2B and B2C channels</p></div>@if(\Illuminate\Support\Facades\Route::has('admin.bookings.index'))<a class="rt-text-link" href="{{ route('admin.bookings.index') }}">View all bookings →</a>@endif</div>
    <div class="rt-table-scroll"><table class="rt-table"><thead><tr><th>Booking ID</th><th>Service</th><th>Channel</th><th>Status</th><th>Approved total</th><th>Date</th></tr></thead><tbody>
    @forelse($overview['recentBookings'] as $booking)
        <tr><td class="rt-strong">{{ $booking->booking_no }}</td><td>{{ ucfirst($booking->service_type) }}</td><td>{{ strtoupper($booking->channel) }}</td><td><span class="rt-pill">{{ str_replace('_',' ',ucfirst($booking->status)) }}</span></td><td>@if($booking->approved_total !== null){{ number_format((float)$booking->approved_total,2) }} {{ $booking->currency }}@else — @endif</td><td>{{ \Illuminate\Support\Carbon::parse($booking->created_at)->format('d M Y') }}</td></tr>
    @empty<tr><td colspan="6" class="rt-empty">No bookings available yet.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
