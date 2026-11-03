@extends('b2b.layouts.app')
@section('title', 'My Company Markup')
@section('content')
<div class="mx-auto max-w-3xl space-y-6 py-6"><div><h1 class="text-3xl font-bold">My Company Markup</h1><p class="mt-2 text-sm text-slate-500">Your markup is added on top of your company's Royal selling price. Fixed amounts are USD.</p></div>
@if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>@endif
@if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-red-800">{{ implode(' · ', $errors->all()) }}</div>@endif
@if(!$hasRoyalPrice)<div class="rounded-xl border border-amber-200 bg-amber-50 p-4">Royal has not configured your company's hotel pricing yet.</div>@endif
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="mb-4 text-lg font-bold">Company Selling Markup</h2><form method="POST" action="{{ route('b2b.pricing.save') }}" class="space-y-4">@csrf
<label class="block text-sm font-medium">Percentage %<input class="mt-2 w-full rounded-lg border border-slate-300 p-3" type="number" min="0" max="100" step="0.000001" name="percentage" required value="{{ old('percentage', $companyRule['percentage'] ?? '0') }}"></label>
<label class="block text-sm font-medium">Fixed USD<input class="mt-2 w-full rounded-lg border border-slate-300 p-3" type="number" min="0" max="1000000" step="0.01" name="fixed" required value="{{ old('fixed', $companyRule['fixed'] ?? '0') }}"></label>
<button @disabled(!$hasRoyalPrice) class="rounded-lg bg-[#02143C] px-5 py-3 font-semibold text-white disabled:opacity-40">Save My Markup</button></form></section></div>
@endsection
