@extends('admin.layouts.app')
@section('title', 'Booking Details')
@section('content')
@php
    $state = $attempt->state ?? null;
    $status = $booking->status;
    $tone = match($status) { 'confirmed', 'completed' => 'success', 'pending', 'pending_supplier' => 'warning', 'failed', 'cancelled' => 'danger', default => 'neutral' };
@endphp
<style>
.bd-page{--navy:#02143C;--gold:#B7731E;--muted:#667085;--line:#e6e9ef;color:var(--navy);max-width:1440px;margin:auto;font-size:14px;line-height:1.55}.bd-page *{box-sizing:border-box}.bd-back{display:inline-flex;align-items:center;gap:8px;color:var(--muted);text-decoration:none;font-weight:600;margin-bottom:20px}.bd-back:hover{color:var(--gold)}.bd-page svg{width:20px;height:20px;flex-shrink:0}.bd-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:24px}.bd-head h1{font-size:26px;font-weight:750;margin:4px 0;overflow-wrap:anywhere}.bd-page p{margin:0}.bd-muted{color:var(--muted);font-size:13px}.bd-kicker{font-size:11px;font-weight:700;letter-spacing:1.3px;color:var(--gold);text-transform:uppercase}.bd-pill{display:inline-flex;align-items:center;gap:7px;border-radius:7px;padding:6px 12px;font-size:12px;font-weight:650;text-transform:capitalize}.bd-pill:before{content:'';width:7px;height:7px;border-radius:50%;background:currentColor}.bd-success{background:#e7f5ed;color:#16754a}.bd-warning{background:#fff3df;color:#946000}.bd-danger{background:#fce9e9;color:#b42335}.bd-neutral{background:#edf0f5;color:#526074}.bd-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin-bottom:20px}.bd-card{background:white;border:1px solid var(--line);border-radius:14px;padding:24px;box-shadow:0 2px 5px #02143c04;min-width:0}.bd-card-head{display:flex;align-items:center;gap:12px;margin-bottom:22px}.bd-icon{width:42px;height:42px;border-radius:10px;background:#f6eee4;color:var(--gold);display:grid;place-items:center;flex-shrink:0}.bd-card h2{font-size:16px;font-weight:700;margin:0}.bd-field{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid #eff1f4}.bd-field:last-child{border-bottom:0}.bd-field span:first-child{color:var(--muted)}.bd-field strong{font-weight:650;text-align:right;overflow-wrap:anywhere}.bd-total{background:#f8f9fb;border-radius:10px;padding:18px;margin-top:18px}.bd-total strong{display:block;font-size:27px;font-weight:750;margin-top:4px}.bd-alert{display:flex;align-items:flex-start;gap:10px;padding:14px 16px;border-radius:9px;margin:12px 0;font-size:13px}.bd-alert svg{width:18px;height:18px}.bd-info{background:#eef3fa;color:#344b70}.bd-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:16px}.bd-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 18px;border:1px solid var(--gold);border-radius:9px;background:var(--gold);color:white;font:inherit;font-weight:650;cursor:pointer}.bd-btn:hover{background:#9d6017}.bd-btn-outline{background:white;color:var(--gold)}.bd-btn-outline:hover{background:#f6eee4}.bd-approval{display:flex;align-items:flex-start;gap:10px;background:#f8f9fb;padding:14px;border-radius:9px;margin:14px 0}.bd-approval input{margin-top:4px;accent-color:var(--gold)}.bd-form{margin:0}.bd-cost{font-size:20px;font-weight:700}.bd-page button:focus-visible,.bd-page a:focus-visible,.bd-page input:focus-visible{outline:2px solid var(--gold);outline-offset:3px}@media(max-width:850px){.bd-grid{grid-template-columns:1fr}.bd-card{padding:18px}.bd-head h1{font-size:21px}} 
</style>
<div class="bd-page">
    <a href="{{ route('admin.bookings.index') }}" class="bd-back"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7M5 12h14"/></svg> Back to bookings</a>
    <header class="bd-head"><div><div class="bd-kicker">Booking details</div><h1>{{ $booking->booking_no }}</h1><p class="bd-muted">Booking overview and supplier confirmation</p></div><span class="bd-pill bd-{{ $tone }}">{{ str_replace('_', ' ', $status) }}</span></header>
    @if(session('notice'))<div class="bd-alert bd-success" role="status">{{ session('notice') }}</div>@endif
    @foreach($errors->all() as $error)<div class="bd-alert bd-danger" role="alert">{{ $error }}</div>@endforeach
    <div class="bd-grid">
        <section class="bd-card">
            <div class="bd-card-head"><span class="bd-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></span><div><h2>Booking summary</h2><p class="bd-muted">Reservation information</p></div></div>
            <div class="bd-field"><span>Booking reference</span><strong>{{ $booking->booking_no }}</strong></div>
            <div class="bd-field"><span>Booking status</span><span class="bd-pill bd-{{ $tone }}">{{ str_replace('_', ' ', $status) }}</span></div>
            <div class="bd-total"><span class="bd-muted">Customer total</span><strong>@if(isset($price->customer_total)){{ number_format((float)$price->customer_total, 2) }} {{ $booking->currency }}@else Not available @endif</strong></div>
        </section>
        <section class="bd-card">
            <div class="bd-card-head"><span class="bd-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h4"/></svg></span><div><h2>Supplier information</h2><p class="bd-muted">Confirmation and external reference</p></div></div>
            <div class="bd-field"><span>Supplier attempt</span><strong>{{ $state ? str_replace('_', ' ', $state) : 'Not prepared' }}</strong></div>
            <div class="bd-field"><span>Supplier reference</span><strong>{{ $reference ?? 'Not available' }}</strong></div>
            @if($eligible && $preview)<div class="bd-total"><span class="bd-muted">Verified supplier cost</span><strong>{{ $preview['supplier_cost'] }} USD</strong></div>@endif
        </section>
    </div>
    @if($eligible)
        <section class="bd-card">
            <div class="bd-card-head"><span class="bd-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg></span><div><h2>Sandbox booking actions</h2><p class="bd-muted">Supplier confirmation workflow</p></div></div>
            <div class="bd-alert bd-info" role="note">Sandbox only — no customer payment is collected by this workflow.</div>
            @if($booking->status === 'draft' && in_array($attempt->state ?? null, [null, 'prepared'], true))
                <form method="POST" action="{{ route('admin.bookings.sandbox.prepare', $booking->id) }}" class="bd-form">@csrf
                    <button type="submit" class="bd-btn">Prepare sandbox booking form</button>
                </form>
            @endif
            @if($preview)
                @if($preview['finish_enabled'])
                    <form method="POST" action="{{ route('admin.bookings.sandbox.finish', $booking->id) }}" class="bd-form">@csrf
                        <input type="hidden" name="supplier_cost" value="{{ $preview['supplier_cost'] }}">
                        <label class="bd-approval"><input type="checkbox" name="approve_sandbox" value="1" required><span>I approve this sandbox supplier booking at the displayed cost of <strong>{{ $preview['supplier_cost'] }} USD</strong>.</span></label>
                        <button type="submit" class="bd-btn">Confirm sandbox booking</button>
                    </form>
                @else <div class="bd-alert bd-warning" role="status">Sandbox Finish is disabled by configuration.</div> @endif
            @elseif(($attempt->state ?? null) === 'form_ready')
                <div class="bd-alert bd-warning" role="alert"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2 21h20L12 3Z"/><path d="M12 9v5m0 3v1"/></svg><div><strong>Booking preview unavailable or expired</strong><p>Do not send Finish.</p></div></div>
            @endif
            @if(in_array($attempt->state ?? null, ['finish_unknown','processing','confirmed'], true))
                <form method="POST" action="{{ route('admin.bookings.sandbox.reconcile', $booking->id) }}" class="bd-form bd-actions">@csrf
                    <button type="submit" class="bd-btn bd-btn-outline">Check supplier status</button>
                </form>
            @elseif(($attempt->state ?? null) === 'form_unknown')
                <div class="bd-alert bd-danger" role="alert">Form outcome uncertain. Manual investigation required.</div>
            @endif
        </section>
    @endif
</div>
@endsection
