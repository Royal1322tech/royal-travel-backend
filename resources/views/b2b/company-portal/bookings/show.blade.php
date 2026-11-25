@extends('b2b.company-portal.layout')

@section('title', 'Booking Details')

@section('content')

<style>

    .detail-panel {
        background: #fff;
        border: 1px solid #e1e6ef;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 24px;
    }

    .detail-label {
        color: #64748b;
        font-size: 13px;
        margin-bottom: 8px;
    }

    .detail-value {
        color: #10254b;
        font-size: 16px;
        font-weight: 600;
    }

    .guest-table {
        width: 100%;
        border-collapse: collapse;
    }

    .guest-table th {
        background: #f5f7fb;
        text-align: left;
        padding: 14px;
        color: #64748b;
    }

    .guest-table td {
        padding: 15px;
        border-bottom: 1px solid #edf0f5;
    }

    .back-link {
        color: #10254b;
        text-decoration: none;
        font-weight: 600;
    }

</style>

<div style="margin-bottom:24px">

    <a class="back-link"
       href="{{ route('company-portal.bookings.index') }}">

        &larr; Back to Bookings

    </a>

    <h1>Booking Details</h1>

    <p style="color:#64748b">
        {{ $booking->booking_no }}
    </p>

</div>

<section class="detail-panel">

    <h2 style="margin-top:0">Booking Information</h2>

    <div class="detail-grid">

        <div>
            <div class="detail-label">Booking Reference</div>
            <div class="detail-value">{{ $booking->booking_no }}</div>
        </div>

        <div>
            <div class="detail-label">Order Reference</div>
            <div class="detail-value">{{ $booking->order_no }}</div>
        </div>

        <div>
            <div class="detail-label">Status</div>

            <div class="detail-value">
                {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
            </div>
        </div>

        <div>
            <div class="detail-label">Booked By</div>

            <div class="detail-value">
                {{ $booking->employee_name ?? 'Unknown user' }}
            </div>
        </div>

        <div>
            <div class="detail-label">Created At</div>
            <div class="detail-value">{{ $booking->created_at }}</div>
        </div>

        <div>
            <div class="detail-label">Confirmed At</div>

            <div class="detail-value">
                {{ $booking->confirmed_at ?? '—' }}
            </div>
        </div>

    </div>

</section>

<section class="detail-panel">

    <h2 style="margin-top:0">Hotel Information</h2>

    <div class="detail-grid">

        <div>
            <div class="detail-label">Hotel Name</div>

            <div class="detail-value">
                {{ $booking->hotel_name ?? 'Hotel unavailable' }}
            </div>
        </div>

        <div>
            <div class="detail-label">Destination</div>

            <div class="detail-value">
                {{ $booking->city_name ?? '—' }}
                {{ $booking->country_code ?? '' }}
            </div>
        </div>

        <div>
            <div class="detail-label">Check-in</div>
            <div class="detail-value">{{ $booking->check_in ?? '—' }}</div>
        </div>

        <div>
            <div class="detail-label">Check-out</div>
            <div class="detail-value">{{ $booking->check_out ?? '—' }}</div>
        </div>

        <div>
            <div class="detail-label">Number of Rooms</div>
            <div class="detail-value">{{ $booking->rooms_count ?? '—' }}</div>
        </div>

    </div>

</section>

<section class="detail-panel">

    <h2 style="margin-top:0">Guests</h2>

    <div style="overflow-x:auto">

        <table class="guest-table">

            <thead>
                <tr>
                    <th>Guest Name</th>
                    <th>Type</th>
                    <th>Nationality</th>
                    <th>Room Index</th>
                    <th>Lead Guest</th>
                </tr>
            </thead>

            <tbody>

                @forelse($guests as $guest)

                    <tr>

                        <td>
                            {{ $guest->first_name }}
                            {{ $guest->last_name }}
                        </td>

                        <td>
                            {{ ucfirst($guest->guest_type) }}
                        </td>

                        <td>
                            {{ $guest->nationality_code ?? '—' }}
                        </td>

                        <td>
                            {{ $guest->room_index ?? '—' }}
                        </td>

                        <td>
                            {{ $guest->is_lead ? 'Yes' : 'No' }}
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="5"
                            style="text-align:center;color:#64748b">

                            No guest records found.

                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</section>

<section class="detail-panel">

    <h2 style="margin-top:0">Booking Price</h2>

    <div class="detail-label">
        Approved Booking Total
    </div>

    <div style="font-size:30px;font-weight:700">

        @if($booking->approved_total !== null)

            {{ $booking->currency }}
            {{ number_format((float) $booking->approved_total, 2) }}

        @else

            Not available

        @endif

    </div>

    <p style="color:#64748b;font-size:13px">
        Stored booking price. No payment operations are available.
    </p>

</section>

@endsection