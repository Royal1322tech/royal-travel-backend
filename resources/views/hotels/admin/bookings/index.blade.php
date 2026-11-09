@extends('admin.layouts.app')

@section('title', 'Bookings')

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-[#02143C]">
            Bookings Management
        </h1>

        <p class="mt-1 text-sm text-slate-500">
            Manage bookings across Royal Travel channels
        </p>
    </div>

    <form method="GET"
          action="{{ route('admin.bookings.index') }}"
          class="grid grid-cols-1 gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-3 xl:grid-cols-6">

        <input
            name="status"
            value="{{ $filters['status'] ?? '' }}"
            placeholder="Booking status"
            class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
        >

        <input
            name="service_type"
            value="{{ $filters['service_type'] ?? '' }}"
            placeholder="Service type"
            class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
        >

        <input
            name="channel"
            value="{{ $filters['channel'] ?? '' }}"
            placeholder="Channel"
            class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
        >

        <input
            type="date"
            name="from"
            value="{{ $filters['from'] ?? '' }}"
            class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
        >

        <input
            type="date"
            name="to"
            value="{{ $filters['to'] ?? '' }}"
            class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
        >

        <button
            type="submit"
            class="rounded-xl bg-[#02143C] px-4 py-2 text-sm font-semibold text-white hover:bg-[#102858]">
            Apply Filters
        </button>

    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">

        <div class="border-b border-slate-100 px-6 py-5">
            <h2 class="font-semibold text-[#02143C]">
                All Bookings
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                {{ number_format($bookings->total()) }} records
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-6 py-4">Booking</th>
                        <th class="px-6 py-4">Service</th>
                        <th class="px-6 py-4">Channel</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Created</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($bookings as $booking)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-semibold text-[#02143C]">
                                {{ $booking->booking_no ?? $booking->id }}
                            </td>

                            <td class="px-6 py-4">
                                {{ $booking->service_type ?? '—' }}
                            </td>

                            <td class="px-6 py-4 uppercase">
                                {{ $booking->channel ?? '—' }}
                            </td>

                            <td class="px-6 py-4">
                                <span class="rounded-lg bg-slate-100 px-3 py-1 text-xs">
                                    {{ $booking->status ?? '—' }}
                                </span>
                            </td>

                            <td class="px-6 py-4 text-slate-500">
                                {{ \Illuminate\Support\Carbon::parse($booking->created_at)->format('d M Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5"
                                class="px-6 py-12 text-center text-slate-400">
                                No bookings found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 px-6 py-4">
            {{ $bookings->links() }}
        </div>

    </div>
</div>
@endsection