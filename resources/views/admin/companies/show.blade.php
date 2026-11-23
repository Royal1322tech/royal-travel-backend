
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Company Details | Royal Travel</title>
    <style>
        :root { --navy:#02143C; --gold:#B7731E; --border:#E5EAF1; }
        * { box-sizing:border-box; }
        body { margin:0; background:#F7F9FC; color:#17243B; font-family:Inter,Arial,sans-serif; }
        .container { max-width:1100px; margin:42px auto; padding:0 24px; }
        .header { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:center; margin-bottom:25px; }
        h1,h2 { color:var(--navy); }
        h1 { margin:8px 0; }
        h2 { font-size:18px; margin:0 0 20px; }
        .muted { color:#64748B; font-size:13px; }
        .card { background:white; padding:26px; border:1px solid var(--border); border-radius:16px; margin-bottom:20px; }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; }
        .item span { display:block; font-size:12px; color:#64748B; margin-bottom:7px; }
        .item strong { overflow-wrap:anywhere; }
        .btn { display:inline-block; background:white; border:1px solid var(--border); color:var(--navy); padding:11px 17px; border-radius:9px; text-decoration:none; cursor:pointer; font-weight:600; }
        .primary { background:var(--navy); color:white; }
        .danger { color:#A32D2D; border-color:#E8C9C9; }
        .toolbar { display:flex; gap:10px; flex-wrap:wrap; }
        .toolbar form { margin:0; }
        .badge { display:inline-block; background:#EEF1F5; padding:6px 12px; border-radius:30px; font-size:12px; }
        .approved { background:#E8F7EF; color:#167347; }
        .suspended { background:#FCEAEA; color:#A32D2D; }
        table { width:100%; border-collapse:collapse; text-align:left; }
        th,td { padding:12px; border-bottom:1px solid var(--border); font-size:13px; }
        .alert { padding:15px; border-radius:10px; margin-bottom:20px; background:#E8F7EF; color:#167347; }
        @media(max-width:650px) { .grid { grid-template-columns:1fr; } .card { overflow-x:auto; } }
    </style>
</head>
<body>
<div class="container">

<section class="card">
    <h2>B2B Finance — USD</h2>
    <div class="grid">
        <div class="item"><span>Wallet</span><strong>${{ number_format($financeAccount['wallet_cents']/100,2) }}</strong></div>
        <div class="item"><span>Credit limit</span><strong>${{ number_format($financeAccount['credit_limit_cents']/100,2) }}</strong></div>
        <div class="item"><span>Credit used</span><strong>${{ number_format($financeAccount['credit_used_cents']/100,2) }}</strong></div>
        <div class="item"><span>Credit available</span><strong>${{ number_format($financeAccount['credit_available_cents']/100,2) }}</strong></div>
    </div>
    <p class="muted">Credit status: {{ $financeAccount['credit_frozen'] ? 'Frozen' : 'Active' }}</p>
    @if(app(\App\Modules\Identity\Services\PermissionService::class)->adminCan(auth()->user(),'companies.manage'))
    <p class="muted">Financial operations require authorized admin access. Every operation needs a unique reference and a reason.</p>
    <form method="POST" action="{{ route('admin.companies.finance.update',$company->id) }}">
        @csrf
        <div class="grid">
            <label>Operation<select name="type" required style="width:100%;padding:10px"><option value="wallet_topup">Top up wallet</option><option value="set_credit_limit">Set credit limit</option><option value="credit_settlement">Record credit repayment</option><option value="credit_freeze">Freeze credit</option><option value="credit_unfreeze">Unfreeze credit</option></select></label>
            <label>Amount (USD; leave blank for freeze)<input name="amount" inputmode="decimal" placeholder="100.00" style="width:100%;padding:10px"></label>
            <label>Unique transaction reference<input name="reference" required maxlength="100" style="width:100%;padding:10px"></label>
            <label>Reason<input name="reason" required minlength="5" maxlength="2000" style="width:100%;padding:10px"></label>
        </div>
        <p><button class="btn primary" type="submit">Record financial operation</button></p>
    </form>
    @endif
    @if($errors->any())<div class="alert" style="background:#fff1f2;color:#9f1239">{{ $errors->first() }}</div>@endif
    <h2>Recent ledger entries</h2>
    <div style="overflow-x:auto"><table><thead><tr><th>Time</th><th>Operation</th><th>Amount (USD)</th><th>Reference</th><th>Reason</th></tr></thead><tbody>
    @forelse($financeEntries as $entry)<tr><td>{{ $entry->created_at }}</td><td>{{ $entry->type }}</td><td>{{ $entry->amount_cents === null ? '—' : number_format($entry->amount_cents/100,2) }}</td><td>{{ $entry->reference }}</td><td>{{ $entry->reason }}</td></tr>
    @empty<tr><td colspan="5">No financial transactions yet.</td></tr>@endforelse
    </tbody></table></div>
</section>

    <div class="header">
        <div>
            <a class="muted" href="{{ route('admin.companies.index') }}">
                Companies / Back to list
            </a>
            <h1>{{ $company->display_name ?: $company->legal_name }}</h1>
            <span class="badge {{ $company->status }}">
                {{ ucfirst($company->status) }}
            </span>
        </div>

        <div class="toolbar">

    <a class="btn primary"
       href="{{ route('admin.companies.edit', $company->id) }}">
        Edit Company
    </a>

    @if($company->status === 'pending')

        <form method="POST"
              action="{{ route('admin.companies.approve', $company->id) }}"
              onsubmit="return confirm('Approve this company and activate its account?')">
            @csrf
            @method('PATCH')

            <button type="submit" class="btn primary">
                Approve Company
            </button>
        </form>

    @elseif($company->status === 'approved')

        <form method="POST"
              action="{{ route('admin.companies.deactivate', $company->id) }}"
              onsubmit="return confirm('Deactivate this company?')">
            @csrf
            @method('PATCH')

            <button type="submit" class="btn danger">
                Deactivate Company
            </button>
        </form>

    @elseif($company->status === 'suspended')

        <form method="POST"
              action="{{ route('admin.companies.activate', $company->id) }}"
              onsubmit="return confirm('Reactivate this company?')">
            @csrf
            @method('PATCH')

            <button type="submit" class="btn primary">
                Activate Company
            </button>
        </form>

    @endif

</div>
    </div>

    @if(session('success'))
        <div class="alert">{{ session('success') }}</div>
    @endif

    <div class="card">
        <h2>Company Information</h2>
        <div class="grid">
            <div class="item"><span>Legal Name</span><strong>{{ $company->legal_name }}</strong></div>
            <div class="item"><span>Business Type</span><strong>{{ $company->business_type }}</strong></div>
            <div class="item"><span>Registration Number</span><strong>{{ $company->registration_no }}</strong></div>
            <div class="item"><span>Country</span><strong>{{ $company->country_code }}</strong></div>
            <div class="item"><span>City</span><strong>{{ $company->city }}</strong></div>
            <div class="item"><span>Employees</span><strong>{{ $company->employee_count ?? '—' }}</strong></div>
            <div class="item"><span>Address</span><strong>{{ $company->address }}</strong></div>
            <div class="item"><span>Created</span><strong>{{ $company->created_at?->format('d M Y') }}</strong></div>
        </div>
    </div>

    <div class="card">
        <h2>Company Contacts</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th><th>Email</th><th>Phone</th><th>Type</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contacts as $contact)
                    <tr>
                        <td>{{ $contact->full_name }}</td>
                        <td>{{ $contact->email ?? '—' }}</td>
                        <td>{{ $contact->phone }}</td>
                        <td>{{ ucfirst($contact->contact_type) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No contacts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Company Users</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th><th>Email</th><th>Role</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($members as $member)
                    <tr>
                        <td>{{ $member->name }}</td>
                        <td>{{ $member->email }}</td>
                        <td>{{ $member->role_code }}</td>
                        <td>{{ ucfirst($member->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
