@extends('b2b.company-portal.layout')

@section('title', 'Bookings')

@section('content')

<style>
    .booking-panel {
        background: #fff;
        border: 1px solid #e1e6ef;
        border-radius: 12px;
        padding: 24px;
        margin-top: 24px;
    }

    .booking-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: end;
        gap: 12px;
        margin-bottom: 24px;
    }

    .booking-field {
        flex: 1;
        min-width: 140px;
    }

    .booking-field label {
        display: block;
        font-size: 13px;
        margin-bottom: 8px;
        color: #64748b;
    }

    .booking-field input,
    .booking-field select {
        width: 100%;
        box-sizing: border-box;
        padding: 11px;
        border: 1px solid #d6deea;
        border-radius: 8px;
        background: #fff;
        color: #10254b;
    }

    .booking-table {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    .booking-table th {
        background: #f5f7fb;
        text-align: left;
        color: #64748b;
        font-size: 13px;
        padding: 15px;
    }

    .booking-table td {
        padding: 17px 15px;
        border-bottom: 1px solid #edf0f5;
        font-size: 14px;
    }

    .booking-status {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        background: #edf1f6;
        color: #475569;
    }

    .status-confirmed {
        background: #e5f6ec;
        color: #18794e;
    }

    .status-pending {
        background: #fff3d6;
        color: #946200;
    }

    .status-cancelled,
    .status-failed {
        background: #feecec;
        color: #b91c1c;
    }

    .booking-btn {
        display: inline-block;
        padding: 11px 18px;
        background: #10254b;
        color: #fff;
        border-radius: 8px;
        border: none;
        text-decoration: none;
        font-size: 14px;
        cursor: pointer;
    }

    .booking-reset {
        color: #64748b;
        padding: 11px;
        text-decoration: none;
    }

    .booking-empty {
        text-align: center;
        padding: 60px 20px !important;
        color: #64748b;
    }

    .booking-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 24px;
        gap: 12px;
        flex-wrap: wrap;
    }
</style>

<h1>Bookings</h1>

<p style="color:#64748b;margin-bottom:24px">
    View and track your company's hotel bookings.
</p>

<div class="cards">

    <div class="card">
        <div>Total Bookings</div>
        <div class="number">{{ $stats['total'] }}</div>
    </div>

    <div class="card">
        <div>Confirmed</div>
        <div class="number" style="color:#18794e">
            {{ $stats['confirmed'] }}
        </div>
    </div>

    <div class="card">
        <div>Pending</div>
        <div class="number" style="color:#b7791f">
            {{ $stats['pending'] }}
        </div>
    </div>

    <div class="card">
        <div>Cancelled</div>
        <div class="number" style="color:#b91c1c">
            {{ $stats['cancelled'] }}
        </div>
    </div>

</div>

<section class="booking-panel">

    <h2 style="margin-top:0">All Bookings</h2>

    @if($errors->any())
        <div style="color:#b91c1c;margin-bottom:20px">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="GET"
          action="{{ route('company-portal.bookings.index') }}"
          class="booking-filters">

        <div class="booking-field" style="flex:2">
            <label>Search</label>

            <input type="text"
                   name="search"
                   placeholder="Booking, hotel or employee"
                   value="{{ request('search') }}">
        </div>

        <div class="booking-field">

            <label>Status</label>

            <select name="status">

                <option value="">All statuses</option>

                @foreach($statuses as $status)
                    <option value="{{ $status }}"
                        @selected(request('status') === $status)>

                        {{ ucfirst(str_replace('_', ' ', $status)) }}

                    </option>
                @endforeach

            </select>

        </div>

        <div class="booking-field">

            <label>Booked By</label>

            <select name="employee">

                <option value="">All users</option>

                @foreach($employees as $employee)

                    <option value="{{ $employee->id }}"
                        @selected((string) request('employee') === (string) $employee->id)>

                        {{ $employee->name }}

                    </option>

                @endforeach

            </select>

        </div>

        <div class="booking-field">
            <label>Check-in From</label>

            <input type="date"
                   name="from"
                   value="{{ request('from') }}">
        </div>

        <div class="booking-field">
            <label>Check-in To</label>

            <input type="date"
                   name="to"
                   value="{{ request('to') }}">
        </div>

        <button type="submit" class="booking-btn">
            Filter
        </button>

        <a href="{{ route('company-portal.bookings.index') }}"
           class="booking-reset">

            Reset

        </a>

    </form>

    <div style="overflow-x:auto">

        <table class="booking-table">

            <thead>

                <tr>
                    <th>Booking Reference</th>
                    <th>Hotel</th>
                    <th>Booked By</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

                @forelse($bookings as $booking)

                    <tr>

                        <td>
                            <strong>{{ $booking->booking_no }}</strong>

                            <div style="font-size:12px;color:#64748b;margin-top:5px">
                                {{ $booking->order_no }}
                            </div>
                        </td>

                        <td>

                            <strong>
                                {{ $booking->hotel_name ?? 'Hotel unavailable' }}
                            </strong>

                            <div style="font-size:12px;color:#64748b;margin-top:5px">

                                {{ $booking->city_name ?? '' }}
                                {{ $booking->country_code ?? '' }}

                            </div>

                        </td>

                        <td>
                            {{ $booking->employee_name ?? 'Unknown user' }}
                        </td>

                        <td>{{ $booking->check_in ?? '—' }}</td>

                        <td>{{ $booking->check_out ?? '—' }}</td>

                        <td>

                            <span class="booking-status status-{{ $booking->status }}">

                                {{ ucfirst(str_replace('_', ' ', $booking->status)) }}

                            </span>

                        </td>

                        <td>

                            @if($booking->approved_total !== null)

                                <strong>
                                    {{ $booking->currency }}
                                    {{ number_format((float) $booking->approved_total, 2) }}
                                </strong>

                            @else
                                —
                            @endif

                        </td>

                        <td>

                            <a class="booking-btn"
                               href="{{ route('company-portal.bookings.show', $booking->id) }}">

                                View

                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="8" class="booking-empty">

                            <h3>No bookings found</h3>

                            <p>
                                Your company bookings will appear here.
                            </p>

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    <div class="booking-pagination">

        <span style="color:#64748b">

            Showing {{ $bookings->firstItem() ?? 0 }}
            to {{ $bookings->lastItem() ?? 0 }}
            of {{ $bookings->total() }} bookings

        </span>

        <div style="display:flex;gap:10px;align-items:center">

            @if($bookings->previousPageUrl())

                <a class="booking-btn"
                   href="{{ $bookings->previousPageUrl() }}">
                    Previous
                </a>

            @endif

            <span>
                Page {{ $bookings->currentPage() }}
                of {{ $bookings->lastPage() }}
            </span>

            @if($bookings->nextPageUrl())

                <a class="booking-btn"
                   href="{{ $bookings->nextPageUrl() }}">
                    Next
                </a>

            @endif

        </div>

    </div>

</section>

@endsection