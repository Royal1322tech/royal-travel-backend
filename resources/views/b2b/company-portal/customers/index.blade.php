@extends('b2b.company-portal.layout')

@section('title', 'Customers')

@section('content')

<style>
    .customer-panel {
        background: #fff;
        border: 1px solid #e1e6ef;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }

    .customer-table {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    .customer-table th {
        background: #f5f7fb;
        color: #64748b;
        text-align: left;
        padding: 15px;
        font-size: 13px;
    }

    .customer-table td {
        padding: 17px 15px;
        border-bottom: 1px solid #edf0f5;
        font-size: 14px;
    }

    .customer-input {
        padding: 12px;
        border: 1px solid #d6deea;
        border-radius: 8px;
        min-width: 200px;
        flex: 1;
    }

    .customer-btn {
        display: inline-block;
        background: #10254b;
        color: #fff;
        padding: 11px 18px;
        border: none;
        border-radius: 8px;
        text-decoration: none;
        cursor: pointer;
        font-size: 14px;
    }

    .customer-reset {
        color: #64748b;
        text-decoration: none;
        padding: 10px;
    }
</style>

<h1>Customers</h1>

<p style="color:#64748b;margin-bottom:24px">
    View your company's customers and their booking history.
</p>

<div class="cards">

    <div class="card">
        <div>Total Customers</div>
        <div class="number">{{ $totalCustomers }}</div>
    </div>

</div>

<section class="customer-panel" style="margin-top:24px">

    <h2 style="margin-top:0">All Customers</h2>

    @if(session('success'))
        <p style="color:#18794e">
            {{ session('success') }}
        </p>
    @endif

    @if($errors->any())
        @foreach($errors->all() as $error)
            <p style="color:#b91c1c">{{ $error }}</p>
        @endforeach
    @endif

    <form method="GET"
          action="{{ route('company-portal.customers.index') }}"
          style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px">

        <input type="text"
               name="search"
               class="customer-input"
               maxlength="150"
               placeholder="Search by name, email or phone"
               value="{{ request('search') }}">

        <button type="submit" class="customer-btn">
            Search
        </button>

        <a class="customer-reset"
           href="{{ route('company-portal.customers.index') }}">
            Reset
        </a>

    </form>

    <div style="overflow-x:auto">

        <table class="customer-table">

            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Created By</th>
                    <th>Bookings</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                @forelse($customers as $customer)

                    <tr>

                        <td>
                            <strong>
                                {{ $customer->first_name }}
                                {{ $customer->last_name }}
                            </strong>
                        </td>

                        <td>{{ $customer->email ?? '—' }}</td>

                        <td>{{ $customer->phone ?? '—' }}</td>

                        <td>{{ $customer->created_by_name ?? 'Unknown' }}</td>

                        <td>{{ $customer->bookings_count }}</td>

                        <td>
                            {{ \Illuminate\Support\Carbon::parse($customer->created_at)->format('d M Y') }}
                        </td>

                        <td>
                            <a class="customer-btn"
                               href="{{ route('company-portal.customers.show', $customer->id) }}">
                                View
                            </a>
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="7"
                            style="text-align:center;padding:50px;color:#64748b">

                            <h3>No customers found</h3>

                            <p>
                                Your company customers will appear here.
                            </p>

                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:24px;gap:12px;flex-wrap:wrap">

        <span style="color:#64748b">
            Showing {{ $customers->firstItem() ?? 0 }}
            to {{ $customers->lastItem() ?? 0 }}
            of {{ $customers->total() }} customers
        </span>

        <div style="display:flex;gap:10px;align-items:center">

            @if($customers->previousPageUrl())
                <a class="customer-btn"
                   href="{{ $customers->previousPageUrl() }}">
                    Previous
                </a>
            @endif

            <span>
                Page {{ $customers->currentPage() }}
                of {{ $customers->lastPage() }}
            </span>

            @if($customers->nextPageUrl())
                <a class="customer-btn"
                   href="{{ $customers->nextPageUrl() }}">
                    Next
                </a>
            @endif

        </div>

    </div>

</section>

@endsection