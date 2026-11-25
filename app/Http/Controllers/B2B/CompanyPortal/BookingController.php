<?php

namespace App\Http\Controllers\B2B\CompanyPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BookingController extends Controller
{
    private function query(string $companyId)
    {
        return DB::table('rt_bookings as b')
            ->join('rt_orders as o', 'o.id', '=', 'b.order_id')
            ->leftJoin('rt_hotel_booking_details as hd', 'hd.booking_id', '=', 'b.id')
            ->leftJoin('rt_hotels as h', 'h.id', '=', 'hd.hotel_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.created_by')
            ->where('b.company_id', $companyId)
            ->where('o.company_id', $companyId)
            ->where('b.channel', 'b2b')
            ->where('o.channel', 'b2b')
            ->where('b.service_type', 'hotel')
            ->select([
                'b.id',
                'b.booking_no',
                'b.status',
                'b.currency',
                'b.approved_total',
                'b.created_at',
                'b.confirmed_at',
                'o.order_no',
                'o.created_by',
                'u.name as employee_name',
                'h.name_en as hotel_name',
                'h.city_name',
                'h.country_code',
                'hd.check_in',
                'hd.check_out',
                'hd.rooms_count',
            ]);
    }

    public function index(Request $request)
    {
        $company = $request->attributes->get('portal_company');

        abort_unless($company, 403);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'max:50'],
            'employee' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $base = $this->query($company->id);

        $statusCounts = DB::query()
            ->fromSub(clone $base, 'records')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'total' => $statusCounts->sum(),
            'confirmed' => $statusCounts->get('confirmed', 0),
            'pending' => $statusCounts->get('pending', 0),
            'cancelled' => $statusCounts->get('cancelled', 0),
        ];

        $employees = DB::table('rt_company_memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.company_id', $company->id)
            ->whereIn('m.role_code', [
                'company_admin',
                'company_employee',
            ])
            ->select('u.id', 'u.name')
            ->distinct()
            ->orderBy('u.name')
            ->get();

        $query = clone $base;

        if (!empty($data['search'])) {
            $search = '%' . $data['search'] . '%';

            $query->where(function ($q) use ($search) {
                $q->where('b.booking_no', 'ilike', $search)
                    ->orWhere('o.order_no', 'ilike', $search)
                    ->orWhere('h.name_en', 'ilike', $search)
                    ->orWhere('u.name', 'ilike', $search);
            });
        }

        if (!empty($data['status'])) {
            $query->where('b.status', $data['status']);
        }

        if (!empty($data['employee'])) {
            $query->where('o.created_by', $data['employee']);
        }

        if (!empty($data['from'])) {
            $query->whereDate('hd.check_in', '>=', $data['from']);
        }

        if (!empty($data['to'])) {
            $query->whereDate('hd.check_in', '<=', $data['to']);
        }

        $bookings = $query
            ->orderByDesc('b.created_at')
            ->orderByDesc('b.id')
            ->paginate(20)
            ->withQueryString();

        $statuses = $statusCounts->keys()->sort()->values();

        return view(
            'b2b.company-portal.bookings.index',
            compact(
                'company',
                'bookings',
                'employees',
                'statuses',
                'stats'
            )
        );
    }

    public function show(Request $request, string $id)
    {
        $company = $request->attributes->get('portal_company');

        abort_unless($company, 403);

        $booking = $this->query($company->id)
            ->where('b.id', $id)
            ->first();

        abort_unless($booking, 404);

        $guests = DB::table('rt_booking_guests')
            ->where('booking_id', $booking->id)
            ->select([
                'first_name',
                'last_name',
                'guest_type',
                'nationality_code',
                'room_index',
                'is_lead',
            ])
            ->orderBy('room_index')
            ->orderByDesc('is_lead')
            ->get();

        return view(
            'b2b.company-portal.bookings.show',
            compact('company', 'booking', 'guests')
        );
    }
}