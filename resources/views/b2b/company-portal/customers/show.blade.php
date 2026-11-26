@extends('b2b.company-portal.layout')

@section('title', 'Customer Details')

@section('content')

<style>
    .customer-detail-panel {
        background: #fff;
        border: 1px solid #e1e6ef;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }

    .customer-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 25px;
    }

    .customer-label {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 8px;
    }

    .customer-value {
        font-size: 16px;
        color: #10254b;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .history-table {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    .history-table th {
        text-align: left;
        background: #f5f7fb;
        padding: 15px;
        color: #64748b;
    }

    .history-table td {
        padding: 15px;
        border-bottom: 1px solid #edf0f5;
    }

    .history-btn {
        display: inline-block;
        padding: 10px 16px;
        border-radius: 8px;
        background: #10254b;
        color: white;
        text-decoration: none;
    }
</style>

<a href="{{ route('company-portal.customers.index') }}"
   style="color:#10254b;text-decoration:none">

    &larr; Back to Customers

</a>

<h1>Customer Details</h1>

<section class="customer-detail-panel">

    <h2 style="margin-top:0">
        {{ $customer->first_name }}
        {{ $customer->last_name }}
    </h2>

    <div class="customer-detail-grid">

        <div>
            <div class="customer-label">First Name</div>
            <div class="customer-value">{{ $customer->first_name }}</div>
        </div>

        <div>
            <div class="customer-label">Last Name</div>
            <div class="customer-value">{{ $customer->last_name }}</div>
        </div>

        <div>
            <div class="customer-label">Email</div>
            <div class="customer-value">{{ $customer->email ?? '—' }}</div>
        </div>

        <div>
            <div class="customer-label">Phone</div>
            <div class="customer-value">{{ $customer->phone ?? '—' }}</div>
        </div>

        <div>
            <div class="customer-label">Created By</div>
            <div class="customer-value">{{ $customer->created_by_name ?? 'Unknown' }}</div>
        </div>

        <div>
            <div class="customer-label">Created At</div>
            <div class="customer-value">
                {{ \Illuminate\Support\Carbon::parse($customer->created_at)->format('d M Y') }}
            </div>
        </div>

    </div>

</section>

<section class="customer-detail-panel">

    <h2 style="margin-top:0">Booking History</h2>

    <div style="overflow-x:auto">

        <table class="history-table">

            <thead>
                <tr>
                    <th>Booking Reference</th>
                    <th>Booked By</th>
                    <th>Status</th>
                    <th>Agency Purchase Price</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                @forelse($bookings as $booking)

                    <tr>

                        <td>{{ $booking->booking_no }}</td>

                        <td>{{ $booking->employee_name ?? 'Unknown' }}</td>

                        <td>
                            {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                        </td>

                        <td>
                            @if($booking->approved_total !== null)

                                {{ $booking->currency }}
                                {{ number_format((float) $booking->approved_total, 2) }}

                            @else
                                —
                            @endif
                        </td>

                        <td>
                            {{ \Illuminate\Support\Carbon::parse($booking->created_at)->format('d M Y') }}
                        </td>

                        <td>
                            <a class="history-btn"
                               href="{{ route('company-portal.bookings.show', $booking->id) }}">
                                View Booking
                            </a>
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="6"
                            style="text-align:center;padding:40px;color:#64748b">

                            No bookings found for this customer.

                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    <div style="display:flex;gap:12px;align-items:center;margin-top:24px">

        @if($bookings->previousPageUrl())
            <a class="history-btn"
               href="{{ $bookings->previousPageUrl() }}">
                Previous
            </a>
        @endif

        <span>
            Page {{ $bookings->currentPage() }}
            of {{ $bookings->lastPage() }}
        </span>

        @if($bookings->nextPageUrl())
            <a class="history-btn"
               href="{{ $bookings->nextPageUrl() }}">
                Next
            </a>
        @endif

    </div>

</section>

@endsection