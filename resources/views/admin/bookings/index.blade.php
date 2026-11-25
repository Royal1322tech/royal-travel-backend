@extends('admin.layouts.app')
@section('title', 'Bookings')
@section('content')
@php
    $pageRows = collect($bookings->items());
    $cards = [
        ['Total results', $bookings->total(), 'calendar', 'navy', 'Matching filters'],
        ['Confirmed', $pageRows->where('status','confirmed')->count(), 'check', 'green', 'On this page'],
        ['Pending', $pageRows->whereIn('status',['pending','pending_supplier'])->count(), 'clock', 'gold', 'On this page'],
        ['Cancelled', $pageRows->where('status','cancelled')->count(), 'cancel', 'red', 'On this page'],
        ['Draft', $pageRows->where('status','draft')->count(), 'file', 'purple', 'On this page'],
    ];
    $icons = [
        'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 10h18"/>',
        'check'=>'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4m-10 8 3 3 5-6"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'cancel'=>'<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>',
        'file'=>'<path d="M6 3h8l4 4v14H6zM14 3v5h4M9 12h6m-6 4h6"/>',
        'hotel'=>'<path d="M3 18V6m0 9h18v5m-18-2h18M7 15V9h5v6m0-4h6a3 3 0 0 1 3 3v1"/>',
        'eye'=>'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'edit'=>'<path d="m14 5 5 5M4 20l5-1L21 7l-5-5L4 14z"/>',
        'trash'=>'<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>',
    ];
@endphp
<style>
.rb-page{--navy:#02143c;--gold:#b7731e;--line:#e7e9ed;--muted:#667085;color:var(--navy);font-size:14px;display:grid;gap:22px}.rb-page *{box-sizing:border-box}.rb-page h1,.rb-page h2,.rb-page p{margin:0}.rb-page h1{font-size:26px;font-weight:750;letter-spacing:-.5px}.rb-page h2{font-size:16px;font-weight:700}.rb-sub{color:var(--muted);margin-top:5px!important;font-size:13px}.rb-cards{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}.rb-card,.rb-panel{background:#fff;border:1px solid var(--line);border-radius:15px;box-shadow:0 2px 5px #02143c05}.rb-card{padding:17px;display:flex;gap:12px;align-items:flex-start}.rb-icon{display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;width:38px;height:38px;border-radius:10px;background:#e9eef8;color:#234780}.rb-page svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}.rb-icon.green{background:#e5f5ed;color:#16834d}.rb-icon.gold{background:#fff2dc;color:#b7731e}.rb-icon.red{background:#fde9e9;color:#b42332}.rb-icon.purple{background:#f1eafa;color:#8051a4}.rb-card-label{font-size:12px;font-weight:600;color:#475467}.rb-value{display:block;font-size:25px;font-weight:750;line-height:1.3}.rb-note{font-size:11px;color:var(--muted)}.rb-panel{padding:20px}.rb-filter-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.rb-field{display:grid;gap:7px;min-width:0}.rb-field label{font-size:13px;font-weight:600;color:#475467}.rb-field input{width:100%;height:43px;border:1px solid #dce0e5;border-radius:10px;background:#fff;padding:0 12px;color:var(--navy);font:inherit}.rb-field input:focus{outline:2px solid #b7731e55;border-color:var(--gold)}.rb-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}.rb-btn{display:inline-flex;align-items:center;justify-content:center;min-width:112px;padding:11px 18px;border:1px solid var(--gold);border-radius:10px;font-weight:650;text-decoration:none;color:var(--gold);background:#fff;cursor:pointer}.rb-btn.primary{background:var(--gold);color:#fff}.rb-btn:hover{filter:brightness(.96)}.rb-table-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}.rb-scroll{overflow-x:auto}.rb-table{width:100%;border-collapse:separate;border-spacing:0;text-align:start;white-space:nowrap;font-size:13px}.rb-table th{text-align:start;background:#f1f3f5;color:#475467;font-weight:650;padding:14px 16px}.rb-table th:first-child{border-radius:9px 0 0 9px}.rb-table th:last-child{border-radius:0 9px 9px 0}.rb-table td{padding:16px;border-bottom:1px solid #eef0f3;color:#344054}.rb-table tr:hover td{background:#fafbfc}.rb-id{color:var(--navy);font-weight:650;text-decoration:none}.rb-id:hover{text-decoration:underline}.rb-service{display:inline-flex;align-items:center;gap:8px}.rb-service svg{color:var(--gold);width:18px;height:18px}.rb-status{display:inline-flex;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:600;background:#edf0f4;color:#475467}.rb-status.confirmed,.rb-status.completed{background:#e5f5ed;color:#167044}.rb-status.pending,.rb-status.pending_supplier{background:#fff1d9;color:#946000}.rb-status.cancelled,.rb-status.failed{background:#fde9e9;color:#b42332}.rb-status.draft{background:#eee9fa;color:#6941a5}.rb-row-actions{display:inline-flex;gap:4px}.rb-action{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #e1e5eb;border-radius:7px;color:#526074;background:#fff}.rb-action svg{width:16px;height:16px}.rb-action:disabled{opacity:.45;cursor:not-allowed}.rb-action:not(:disabled):hover{color:var(--gold);border-color:var(--gold)}.rb-pagination{padding-top:18px}.rb-empty{text-align:center;padding:40px!important;color:var(--muted)!important}@media(max-width:1200px){.rb-cards{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:700px){.rb-cards,.rb-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.rb-panel{padding:14px}.rb-card{padding:12px;gap:8px}}@media(max-width:420px){.rb-cards,.rb-filter-grid{grid-template-columns:1fr}}
</style>
<div class="rb-page">
    <header><h1>Bookings Management</h1><p class="rb-sub">Manage bookings across Royal Travel channels</p></header>
    <div class="rb-cards">
        @foreach($cards as [$label,$value,$icon,$tone,$note])
            <article class="rb-card"><span class="rb-icon {{ $tone }}"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$icon] !!}</svg></span><div><div class="rb-card-label">{{ $label }}</div><strong class="rb-value">{{ number_format($value) }}</strong><span class="rb-note">{{ $note }}</span></div></article>
        @endforeach
    </div>
    <form method="GET" action="{{ route('admin.bookings.index') }}" class="rb-panel">
        <h2>Filter bookings</h2><p class="rb-sub" style="margin-bottom:18px">Refine your booking results</p>
        <div class="rb-filter-grid">
            <div class="rb-field"><label for="rb-status">Booking status</label><input id="rb-status" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="All statuses"></div>
            <div class="rb-field"><label for="rb-service">Service type</label><input id="rb-service" name="service_type" value="{{ $filters['service_type'] ?? '' }}" placeholder="All services"></div>
            <div class="rb-field"><label for="rb-channel">Channel</label><input id="rb-channel" name="channel" value="{{ $filters['channel'] ?? '' }}" placeholder="All channels"></div>
            <div class="rb-field"><label for="rb-from">From date</label><input id="rb-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="rb-field"><label for="rb-to">To date</label><input id="rb-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
        </div>
        <div class="rb-actions"><a class="rb-btn" href="{{ route('admin.bookings.index') }}">Reset</a><button type="submit" class="rb-btn primary">Apply Filters</button></div>
    </form>
    <section class="rb-panel">
        <div class="rb-table-head"><div><h2>All Bookings</h2><p class="rb-sub">{{ number_format($bookings->total()) }} records</p></div></div>
        <div class="rb-scroll"><table class="rb-table"><thead><tr><th>Booking ID</th><th>Service</th><th>Channel</th><th>Date</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @forelse($bookings as $booking)
            <tr>
                <td><a class="rb-id" href="{{ route('admin.bookings.show', $booking->id) }}">{{ $booking->booking_no ?? $booking->id }}</a></td>
                <td><span class="rb-service"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[($booking->service_type ?? '') === 'hotel' ? 'hotel' : 'file'] !!}</svg>{{ ucfirst($booking->service_type ?? '—') }}</span></td>
                <td>{{ strtoupper($booking->channel ?? '—') }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($booking->created_at)->format('d M Y') }}</td>
                <td><span class="rb-status {{ $booking->status ?? '' }}">{{ ucfirst(str_replace('_',' ', $booking->status ?? '—')) }}</span></td>
                <td><div class="rb-row-actions"><a class="rb-action" href="{{ route('admin.bookings.show', $booking->id) }}" title="View booking" aria-label="View booking"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons['eye'] !!}</svg></a><button class="rb-action" type="button" disabled title="Edit — coming soon" aria-label="Edit — coming soon"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons['edit'] !!}</svg></button><button class="rb-action" type="button" disabled title="Delete — coming soon" aria-label="Delete — coming soon"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons['trash'] !!}</svg></button></div></td>
            </tr>
        @empty<tr><td colspan="6" class="rb-empty">No bookings found.</td></tr>@endforelse
        </tbody></table></div>
        <div class="rb-pagination">{{ $bookings->links() }}</div>
    </section>
</div>
@endsection
