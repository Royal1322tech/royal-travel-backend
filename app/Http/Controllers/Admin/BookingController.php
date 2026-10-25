<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminBookingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
}