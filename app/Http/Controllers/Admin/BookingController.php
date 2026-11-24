<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminBookingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Hotels\AdminRateHawkBookingConfirmationService;
use App\Services\Hotels\AdminRateHawkBookingFinishService;

class BookingController extends Controller
{
    public function __construct(
        private readonly AdminBookingService $bookingService
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'service_type' => ['nullable', 'string', 'max:50'],
            'channel' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return view('admin.bookings.index', [
            'pageTitle' => 'Bookings',
            'bookings' => $this->bookingService->paginate($filters),
            'filters' => $filters,
        ]);
    }

    private function context(string $id): array
    {
        $booking = DB::table('rt_bookings')->where('id', $id)->first();
        abort_unless($booking, 404);
        $offer = DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $id)->first();
        $attempt = DB::table('rt_supplier_booking_attempts')->where('booking_id', $id)
            ->where('operation', 'ratehawk_hotel_booking')->first();
        $eligible = $booking->channel === 'b2c' && $booking->company_id === null
            && $booking->service_type === 'hotel' && $booking->currency === 'USD'
            && ($offer->environment ?? null) === 'sandbox'
            && config('services.ratehawk.environment') === 'sandbox'
            && config('services.ratehawk.sandbox_url') === 'https://api-sandbox.ratehawk.com';
        $price = DB::table('rt_booking_price_snapshots')->where('booking_id', $id)->orderByDesc('version')->first();
        $reference = $attempt ? DB::table('rt_supplier_external_refs')->where('attempt_id', $attempt->id)
            ->where('reference_kind', 'ratehawk_order_id')->value('reference_value') : null;
        return compact('booking', 'attempt', 'eligible', 'price', 'reference');
    }

    public function show(string $id, AdminRateHawkBookingFinishService $finish): View
    {
        $context = $this->context($id);
        $preview = null;
        if ($context['eligible'] && ($context['attempt']->state ?? null) === 'form_ready') {
            try { $preview = $finish->preview($id); } catch (\Throwable $e) { /* Fail closed. */ }
        }
        return view('admin.bookings.show', $context + ['preview' => $preview]);
    }

    public function prepareSandbox(Request $request, string $id, AdminRateHawkBookingConfirmationService $service)
    {
        $c = $this->context($id);
        abort_unless($c['eligible'] && $c['booking']->status === 'draft', 409);
        try {
            $attempt = $service->prepare($id);
            if ($attempt['state'] === 'prepared') { $service->createForm($id, $request->ip()); }
            return redirect()->route('admin.bookings.show', $id)->with('notice', 'Preparation checked. Review current attempt state.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.bookings.show', $id)->withErrors(['booking' => 'Preparation failed or is uncertain. Review state; do not blindly retry.']);
        }
    }

    public function finishSandbox(Request $request, string $id, AdminRateHawkBookingFinishService $service)
    {
        $c = $this->context($id);
        abort_unless($c['eligible'] && config('services.ratehawk.allow_sandbox_finish') === true, 403);
        $v = $request->validate(['approve_sandbox' => ['accepted'], 'supplier_cost' => ['required', 'regex:/^[0-9]+(?:\.[0-9]{1,6})?$/D']]);
        try {
            $p = $service->preview($id);
            if (bccomp($p['supplier_cost'], $v['supplier_cost'], 6) !== 0) { throw new \RuntimeException('Price changed'); }
            $service->finish($id);
            return redirect()->route('admin.bookings.show', $id)->with('notice', 'Finish submitted. Check supplier status.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.bookings.show', $id)->withErrors(['booking' => 'Finish blocked or outcome uncertain. Review attempt state; never resend an uncertain Finish.']);
        }
    }

    public function reconcileSandbox(string $id, AdminRateHawkBookingFinishService $service)
    {
        abort_unless($this->context($id)['eligible'], 409);
        try {
            $result = $service->reconcile($id);
            return redirect()->route('admin.bookings.show', $id)->with('notice', 'Supplier status: '.$result['status']);
        } catch (\Throwable $e) {
            return redirect()->route('admin.bookings.show', $id)->withErrors(['booking' => 'Status check unavailable. Do not resend Finish.']);
        }
    }
}
