@extends('b2b.company-portal.layout')

@section('title', 'Company Profile')

@section('content')

<style>
    .profile-panel {
        background: #fff;
        border: 1px solid #e1e6ef;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }

    .profile-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 26px;
    }

    .profile-label {
        color: #64748b;
        font-size: 13px;
        margin-bottom: 8px;
    }

    .profile-value {
        font-size: 16px;
        font-weight: 600;
        color: #10254b;
        overflow-wrap: anywhere;
    }

    .profile-stat {
        background: #f5f7fb;
        padding: 20px;
        border-radius: 10px;
    }

    .profile-number {
        font-size: 28px;
        font-weight: 700;
        margin-top: 10px;
        color: #10254b;
    }

    .profile-status {
        display: inline-block;
        background: #e2f4e9;
        color: #18794e;
        padding: 7px 14px;
        border-radius: 8px;
        font-size: 13px;
    }
</style>

<h1>Company Profile</h1>

<p style="color:#64748b;margin-bottom:24px">
    View your company information and account overview.
</p>

<section class="profile-panel">

    <div style="display:flex;justify-content:space-between;align-items:center;gap:15px">

        <div>
            <h2 style="margin:0">
                {{ $profile->display_name ?? $profile->legal_name }}
            </h2>

            <p style="color:#64748b">
                B2B Company Account
            </p>
        </div>

        <span class="profile-status">
            {{ ucfirst($profile->status) }}
        </span>

    </div>

    <div class="profile-grid">

        <div class="profile-stat">
            <div class="profile-label">Active Employees</div>
            <div class="profile-number">{{ $stats['employees'] }}</div>
        </div>

        <div class="profile-stat">
            <div class="profile-label">Customers</div>
            <div class="profile-number">{{ $stats['customers'] }}</div>
        </div>

        <div class="profile-stat">
            <div class="profile-label">Hotel Bookings</div>
            <div class="profile-number">{{ $stats['bookings'] }}</div>
        </div>

    </div>

</section>

<section class="profile-panel">

    <h2 style="margin-top:0">Company Information</h2>

    <div class="profile-grid">

        <div>
            <div class="profile-label">Legal Name</div>
            <div class="profile-value">{{ $profile->legal_name }}</div>
        </div>

        <div>
            <div class="profile-label">Display Name</div>
            <div class="profile-value">{{ $profile->display_name ?? '—' }}</div>
        </div>

        <div>
            <div class="profile-label">Registration Number</div>
            <div class="profile-value">{{ $profile->registration_no ?? '—' }}</div>
        </div>

        <div>
            <div class="profile-label">Tax Number</div>
            <div class="profile-value">{{ $profile->tax_no ?? '—' }}</div>
        </div>

        <div>
            <div class="profile-label">Country</div>
            <div class="profile-value">{{ $profile->country_code ?? '—' }}</div>
        </div>

        <div>
            <div class="profile-label">Account Currency</div>
            <div class="profile-value">{{ $profile->account_currency }}</div>
        </div>

        <div>
            <div class="profile-label">Preferred Language</div>
            <div class="profile-value">{{ strtoupper($profile->preferred_locale ?? 'en') }}</div>
        </div>

        <div>
            <div class="profile-label">Registration Date</div>
            <div class="profile-value">
                {{ \Illuminate\Support\Carbon::parse($profile->created_at)->format('d M Y') }}
            </div>
        </div>

    </div>

</section>

<section class="profile-panel">

    <h2 style="margin-top:0">Owner Information</h2>

    <div class="profile-grid">

        <div>
            <div class="profile-label">Full Name</div>
            <div class="profile-value">{{ $owner->full_name ?? '—' }}</div>
        </div>

        <div>
            <div class="profile-label">Email</div>
            <div class="profile-value">{{ $owner->email ?? '—' }}</div>
        </div>

        <div>
            <div class="profile-label">Phone</div>
            <div class="profile-value">{{ $owner->phone ?? '—' }}</div>
        </div>

        <div>
            <div class="profile-label">Nationality</div>
            <div class="profile-value">{{ $owner->nationality ?? '—' }}</div>
        </div>

        <div>
            <div class="profile-label">Email Verification</div>
            <div class="profile-value">
                {{ !empty($owner->email_verified_at) ? 'Verified' : 'Not verified' }}
            </div>
        </div>

    </div>

</section>

<section class="profile-panel">

    <h2 style="margin-top:0">Wallet Overview</h2>

    <p style="color:#64748b">
        Read-only financial overview — USD
    </p>

    @if($finance)

        <div class="profile-grid">

            <div class="profile-stat">
                <div class="profile-label">Wallet Balance</div>
                <div class="profile-number">
                    ${{ number_format($finance->wallet_cents / 100, 2) }}
                </div>
            </div>

            <div class="profile-stat">
                <div class="profile-label">Reserved Balance</div>
                <div class="profile-number">
                    ${{ number_format($finance->wallet_reserved_cents / 100, 2) }}
                </div>
            </div>

            <div class="profile-stat">
                <div class="profile-label">Credit Limit</div>
                <div class="profile-number">
                    ${{ number_format($finance->credit_limit_cents / 100, 2) }}
                </div>
            </div>

            <div class="profile-stat">
                <div class="profile-label">Credit Used</div>
                <div class="profile-number">
                    ${{ number_format($finance->credit_used_cents / 100, 2) }}
                </div>
            </div>

        </div>

    @else

        <p>No financial account configured.</p>

    @endif

</section>

@endsection