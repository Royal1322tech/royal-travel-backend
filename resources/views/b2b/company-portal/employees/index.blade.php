@extends('b2b.company-portal.layout')

@section('title', 'Employees')

@section('content')

<style>
    .employee-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .employee-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 18px;
    }

    .employee-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
    }

    .employee-form input {
        width: 100%;
        padding: 12px;
        border: 1px solid #d6deea;
        border-radius: 8px;
        box-sizing: border-box;
        background: #fff;
    }

    .employee-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .employee-table th {
        background: #f3f5f8;
        padding: 15px;
        font-size: 13px;
    }

    .employee-table td {
        padding: 16px 15px;
        border-bottom: 1px solid #edf0f5;
    }

    .employee-btn {
        background: #10254b;
        color: white;
        padding: 12px 22px;
        border: 0;
        border-radius: 8px;
        cursor: pointer;
    }

    .employee-status {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 6px;
        background: #e7f5ec;
        color: #18794e;
        font-size: 12px;
    }
</style>

<h1>Employees</h1>

<p style="color:#64748b">
    Manage employees associated with your company.
</p>

@if(session('success'))
    <div style="background:#e7f5ec;color:#18794e;padding:15px;border-radius:8px;margin-bottom:20px">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div style="background:#fef2f2;color:#b91c1c;padding:15px;border-radius:8px;margin-bottom:20px">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<div class="employee-grid">

    <div class="card">
        <div>Total Employees</div>
        <div class="number">{{ $employees->count() }}</div>
    </div>

    <div class="card">
        <div>Active Employees</div>
        <div class="number">
            {{ $employees->where('status', 'active')->count() }}
        </div>
    </div>

    <div class="card">
        <div>Other Membership Statuses</div>
        <div class="number">
            {{ $employees->where('status', '!=', 'active')->count() }}
        </div>
    </div>

</div>

<div class="card" style="margin-bottom:24px">

    <h2 style="margin-top:0">Add Employee</h2>

    <form method="POST"
          action="{{ route('company-portal.employees.store') }}">

        @csrf

        <div class="employee-form">

            <div>
                <label>Full Name</label>
                <input type="text"
                       name="name"
                       value="{{ old('name') }}"
                       required>
            </div>

            <div>
                <label>Email Address</label>
                <input type="email"
                       name="email"
                       value="{{ old('email') }}"
                       required>
            </div>

            <div>
                <label>Password</label>
                <input type="password"
                       name="password"
                       minlength="12"
                       autocomplete="new-password"
                       required>
            </div>

            <div>
                <label>Confirm Password</label>
                <input type="password"
                       name="password_confirmation"
                       minlength="12"
                       autocomplete="new-password"
                       required>
            </div>

        </div>

        <div style="margin-top:20px">
            <button type="submit" class="employee-btn">
                + Create Employee
            </button>
        </div>

    </form>

</div>

<div class="card">

    <h2 style="margin-top:0">All Employees</h2>

    <div style="overflow-x:auto">

        <table class="employee-table">

            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Membership</th>
                    <th>Created At</th>
                </tr>
            </thead>

            <tbody>

                @forelse($employees as $employee)

                    <tr>
                        <td>{{ $employee->name }}</td>
                        <td>{{ $employee->email }}</td>
                        <td>Employee</td>
                        <td>
                            <span class="employee-status">
                                {{ ucfirst($employee->status) }}
                            </span>
                            @if($employee->email_verified_at === null)
                                <div style="font-size:12px;color:#b7791f;margin-top:4px">Email not verified, cannot sign in yet</div>
                            @endif
                        </td>
                        <td>
                            {{ \Carbon\Carbon::parse($employee->created_at)->format('d M Y') }}
                        </td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="5"
                            style="text-align:center;padding:35px;color:#64748b">
                            No employees found.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@endsection