@extends('admin.layouts.app')
@section('title','Dashboard')
@section('content')
@php
$total = (int) $overview['totalBookings'];
$confirmed = (int) $overview['confirmedBookings'];
$pending = (int) $overview['pendingBookings'];
$other = max(0,$total-$confirmed-$pending);
$den = max(1,$total);
$c = min(100,100*$confirmed/$den);
$p = min(100-$c,100*$pending/$den);
$stats = [['Total bookings',$total,'#02143C','▤'],['Confirmed',$confirmed,'#16834d','✓'],['Pending',$pending,'#B7731E','◷'],['Today bookings',$overview['todayBookings'],'#43879a','▦']];
@endphp
<style>
.rh-home{--navy:#02143c;--gold:#b7731e;--line:#e7e9ed;color:var(--navy);display:grid;gap:24px;font-size:14px}.rh-home *{box-sizing:border-box}.rh-heading{display:flex;justify-content:space-between;align-items:center;gap:12px}.rh-heading h1{font-size:25px;margin:0 0 5px;font-weight:700}.rh-muted{color:#737d8b;font-size:12px}.rh-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.rh-card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:20px;box-shadow:0 2px 5px #02143c05;min-width:0}.rh-kpi-label{display:flex;align-items:center;gap:9px;font-size:13px;font-weight:600}.rh-icon{display:grid;place-items:center;width:32px;height:32px;border-radius:8px;color:white;font-size:18px}.rh-value{font-size:30px;font-weight:700;margin:16px 0 6px;line-height:1}.rh-panels{display:grid;grid-template-columns:1.15fr 1fr;gap:18px}.rh-home h2{font-size:16px;margin:0;font-weight:700}.rh-panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:22px}.rh-bar-row{display:grid;grid-template-columns:90px 1fr 48px;align-items:center;gap:12px;margin:24px 0}.rh-track{height:12px;background:#f1f3f6;border-radius:5px;overflow:hidden}.rh-fill{height:100%;border-radius:5px}.rh-donut-wrap{display:flex;align-items:center;justify-content:center;gap:24px;flex-wrap:wrap;min-height:200px}.rh-donut{width:170px;height:170px;border-radius:50%;display:grid;place-items:center;flex-shrink:0}.rh-donut-center{width:112px;height:112px;background:white;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center}.rh-donut-center strong{font-size:26px}.rh-legend{flex:1;min-width:125px;display:grid;gap:17px}.rh-legend-row{display:flex;align-items:center;gap:8px;font-size:12px}.rh-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}.rh-legend-row strong{margin-left:auto}.rh-secondary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.rh-secondary .rh-card{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:16px}.rh-secondary strong{font-size:20px}.rh-scroll{overflow-x:auto}.rh-table{width:100%;border-collapse:separate;border-spacing:0;white-space:nowrap;text-align:left;font-size:13px}.rh-table th{background:#f3f4f6;color:#626c7c;font-weight:600;padding:13px 14px}.rh-table th:first-child{border-radius:7px 0 0 7px}.rh-table th:last-child{border-radius:0 7px 7px 0}.rh-table td{padding:17px 14px;border-bottom:1px solid #eef0f3}.rh-table tr:last-child td{border-bottom:0}.rh-table tbody tr:hover{background:#faf9f7}.rh-status{display:inline-block;padding:5px 9px;border-radius:5px;font-size:11px;font-weight:600;background:#edf0f5;color:#596579}.rh-status.confirmed,.rh-status.completed{background:#e8f5ed;color:#16834d}.rh-status.pending,.rh-status.pending_supplier{background:#fff2dc;color:#a16a13}.rh-status.failed,.rh-status.cancelled{background:#fcebec;color:#b83c43}.rh-link{display:inline-flex;align-items:center;border:1px solid var(--line);border-radius:7px;padding:8px 12px;text-decoration:none;color:var(--navy);font-size:12px}.rh-link:hover{border-color:var(--gold);color:var(--gold)}.rh-empty{text-align:center;color:#737d8b}.rh-date{white-space:nowrap}@media(max-width:1100px){.rh-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.rh-panels{grid-template-columns:1fr}}@media(max-width:600px){.rh-home{gap:14px}.rh-heading{align-items:flex-start;flex-direction:column}.rh-card{padding:14px}.rh-secondary{grid-template-columns:1fr}.rh-kpis{gap:10px}.rh-value{font-size:24px}}
</style>
<div class="rh-home">
<header class="rh-heading"><div><h1>Dashboard</h1><div class="rh-muted">Overview of your Royal Travel operations</div></div><span class="rh-muted rh-date">{{ now()->format('l, d M Y') }}</span></header>
<div class="rh-kpis">
@foreach($stats as [$label,$value,$color,$icon])
<article class="rh-card"><div class="rh-kpi-label"><span class="rh-icon" style="background:{{ $color }}" aria-hidden="true">{{ $icon }}</span>{{ $label }}</div><div class="rh-value">{{ number_format($value) }}</div><span class="rh-muted">{{ $label === 'Today bookings' ? 'Today' : 'All time' }}</span></article>
@endforeach
</div>
<div class="rh-panels">
<section class="rh-card"><div class="rh-panel-head"><h2>Booking overview</h2><span class="rh-muted">All time</span></div>
@foreach([['Confirmed',$confirmed,$c,'#16834d'],['Pending',$pending,$p,'#B7731E'],['Other',$other,max(0,100-$c-$p),'#aab4c3']] as [$label,$value,$pct,$color])
<div class="rh-bar-row"><span>{{ $label }}</span><div class="rh-track"><div class="rh-fill" style="width:{{ $total > 0 ? $pct : 0 }}%;background:{{ $color }}"></div></div><strong>{{ number_format($value) }}</strong></div>
@endforeach
<div class="rh-muted">Based on current booking records.</div></section>
<section class="rh-card"><div class="rh-panel-head"><h2>Bookings by status</h2></div><div class="rh-donut-wrap"><div class="rh-donut" role="img" aria-label="Booking status distribution" style="background:{{ $total > 0 ? 'conic-gradient(#16834d 0% '.$c.'%, #B7731E '.$c.'% '.($c+$p).'%, #dce1e8 '.($c+$p).'% 100%)' : '#edf0f4' }}"><div class="rh-donut-center"><strong>{{ number_format($total) }}</strong><span class="rh-muted">Total bookings</span></div></div><div class="rh-legend">
@foreach([['Confirmed',$confirmed,'#16834d'],['Pending',$pending,'#B7731E'],['Other',$other,'#dce1e8']] as [$label,$value,$color])
<div class="rh-legend-row"><span class="rh-dot" style="background:{{ $color }}"></span>{{ $label }}<strong>{{ $total > 0 ? round(100*$value/$den) : 0 }}%</strong></div>
@endforeach
</div></div></section></div>
<div class="rh-secondary">
@foreach([['Total orders',$overview['totalOrders']],['Registered companies',$overview['totalCompanies']],['Pending refunds',$overview['pendingRefunds']]] as [$label,$value])
<div class="rh-card"><span class="rh-muted">{{ $label }}</span><strong>{{ number_format($value) }}</strong></div>
@endforeach
</div>
<section class="rh-card"><div class="rh-panel-head"><h2>Recent bookings</h2>@if(\Illuminate\Support\Facades\Route::has('admin.bookings.index'))<a class="rh-link" href="{{ route('admin.bookings.index') }}">See all</a>@endif</div><div class="rh-scroll"><table class="rh-table"><thead><tr><th>Booking ID</th><th>Service</th><th>Channel</th><th>Date</th><th>Approved total</th><th>Status</th></tr></thead><tbody>
@forelse($overview['recentBookings'] as $booking)
<tr><td><strong>{{ $booking->booking_no }}</strong></td><td>{{ ucfirst($booking->service_type) }}</td><td>{{ strtoupper($booking->channel) }}</td><td>{{ \Illuminate\Support\Carbon::parse($booking->created_at)->format('d M Y') }}</td><td>@if($booking->approved_total !== null){{ number_format((float)$booking->approved_total,2) }} {{ $booking->currency }}@else — @endif</td><td><span class="rh-status {{ $booking->status }}">{{ str_replace('_',' ',ucfirst($booking->status)) }}</span></td></tr>
@empty<tr><td colspan="6" class="rh-empty">No bookings available yet.</td></tr>@endforelse
</tbody></table></div></section>
</div>
@endsection
