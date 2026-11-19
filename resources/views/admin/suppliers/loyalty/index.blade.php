@extends('admin.layouts.app')
@section('title', 'B2B Loyalty Points')
@section('content')
<div style="max-width:1250px;margin:auto;padding:24px">
 <h1 style="font-size:28px;font-weight:800;color:#071d48">B2B Loyalty Points</h1>
 <p>Manage points earned from confirmed hotel bookings and manual company rewards. Redemption is disabled.</p>
 @if(session('success'))<p style="padding:12px;background:#e9f7ef">{{ session('success') }}</p>@endif
 @if($errors->any())<div style="padding:12px;background:#fff0f0">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
 @php($canManage = auth()->user() !== null)
 <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin:20px 0">
  <section style="background:white;border:1px solid #e3e8f2;border-radius:14px;padding:20px">
   <h2 style="font-size:19px;font-weight:700">Reward Rule</h2>
   <p>Points per 100 units of booking total, per currency. Saving starts a new effective period.</p>
   <form method="POST" action="{{ route('admin.loyalty.rules.save') }}">@csrf
    <label>Company (optional = all companies)</label><select name="company_id" style="display:block;width:100%;padding:9px;margin:8px 0"><option value="">All companies</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->display_name ?: $company->legal_name }}</option>@endforeach</select>
    <label>Currency</label><input name="currency" value="USD" required maxlength="3" style="display:block;width:100%;padding:9px;margin:8px 0">
    <label>Points per 100</label><input type="number" step="0.0001" min="0" max="100000" name="points_per_100" required value="1" style="display:block;width:100%;padding:9px;margin:8px 0">
    <label>Enabled</label><select name="enabled" style="display:block;width:100%;padding:9px;margin:8px 0"><option value="0">No (safe default)</option><option value="1">Yes</option></select>
    <button style="background:#071d48;color:white;padding:10px 18px;border-radius:8px">Save rule</button>
   </form>
  </section>
  <section style="background:white;border:1px solid #e3e8f2;border-radius:14px;padding:20px">
   <h2 style="font-size:19px;font-weight:700">Manual adjustment</h2>
   <form method="POST" action="{{ route('admin.loyalty.adjust') }}">@csrf
    <label>Company</label><select name="company_id" required style="display:block;width:100%;padding:9px;margin:8px 0">@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->display_name ?: $company->legal_name }}</option>@endforeach</select>
    <label>Action</label><select name="action" style="display:block;width:100%;padding:9px;margin:8px 0"><option value="credit">Add points</option><option value="debit">Deduct points</option></select>
    <label>Points</label><input name="points" type="number" min="1" max="1000000" required style="display:block;width:100%;padding:9px;margin:8px 0">
    <label>Reason</label><textarea name="reason" required minlength="5" maxlength="1000" style="display:block;width:100%;padding:9px;margin:8px 0"></textarea>
    <button style="background:#071d48;color:white;padding:10px 18px;border-radius:8px">Record adjustment</button>
   </form>
  </section>
 </div>
 <section style="background:white;border:1px solid #e3e8f2;border-radius:14px;padding:20px;margin:18px 0;overflow-x:auto"><h2 style="font-size:19px;font-weight:700">Active / saved rules</h2>
 <table style="width:100%;text-align:left"><thead><tr><th>Company</th><th>Currency</th><th>Points / 100</th><th>Enabled</th><th>Effective from</th></tr></thead><tbody>@forelse($rules as $rule)<tr><td>{{ $rule->legal_name ?: 'All companies' }}</td><td>{{ $rule->currency }}</td><td>{{ $rule->points_per_100 }}</td><td>{{ $rule->enabled ? 'Yes' : 'No' }}</td><td>{{ $rule->effective_from }}</td></tr>@empty<tr><td colspan="5">No rules yet.</td></tr>@endforelse</tbody></table></section>
 <section style="background:white;border:1px solid #e3e8f2;border-radius:14px;padding:20px;margin:18px 0;overflow-x:auto"><h2 style="font-size:19px;font-weight:700">Company points</h2>
 <table style="width:100%;text-align:left"><thead><tr><th>Company</th><th>Available</th><th>Pending</th></tr></thead><tbody>@foreach($companies as $company)<tr><td>{{ $company->display_name ?: $company->legal_name }}</td><td>{{ $company->balances['available'] }}</td><td>{{ $company->balances['pending'] }}</td></tr>@endforeach</tbody></table>{{ $companies->links() }}</section>
 <section style="background:white;border:1px solid #e3e8f2;border-radius:14px;padding:20px;margin:18px 0;overflow-x:auto"><h2 style="font-size:19px;font-weight:700">Recent transactions</h2>
 <table style="width:100%;text-align:left"><thead><tr><th>Date</th><th>Company</th><th>Type</th><th>Points</th><th>Bucket</th><th>Booking</th><th>Note</th></tr></thead><tbody>@forelse($entries as $entry)<tr><td>{{ $entry->created_at }}</td><td>{{ $entry->legal_name }}</td><td>{{ $entry->entry_type }}</td><td>{{ $entry->points }}</td><td>{{ $entry->balance_bucket }}</td><td>{{ $entry->booking_id ?: '—' }}</td><td>{{ $entry->note }}</td></tr>@empty<tr><td colspan="7">No transactions.</td></tr>@endforelse</tbody></table></section>
</div>
@endsection
