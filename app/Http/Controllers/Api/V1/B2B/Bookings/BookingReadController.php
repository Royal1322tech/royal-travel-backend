<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B\Bookings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Read-only company bookings (drafts and later states) for the B2B frontend.
 *
 * The company comes from the authenticated token (b2b.api.permission middleware), never from the
 * request. Only the customer-facing total is returned: no supplier cost, markups, supplier offer
 * reference or internal IDs of other companies.
 */
final class BookingReadController extends Controller
{
    private const STATUSES = ['draft', 'pending', 'confirmed', 'failed', 'cancelled', 'completed'];

    private function companyId(Request $request): string
    {
        abort_unless($request->user() !== null, 401, 'Unauthenticated.');
        $id = $request->attributes->get('authenticated_company_id');
        abort_unless(is_string($id) && Str::isUuid($id), 403, 'Invalid company context.');

        return strtolower($id);
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(self::STATUSES)],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $query = $this->baseQuery($companyId)->orderByDesc('b.created_at')->orderByDesc('b.id');
        if (isset($data['status'])) {
            $query->where('b.status', $data['status']);
        }

        $page = $query->paginate(20)->through(fn ($row) => $this->summary($row));

        return response()->json($page)->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, string $booking): JsonResponse
    {
        $companyId = $this->companyId($request);
        abort_unless(Str::isUuid($booking), 404, 'Booking not found.');

        $row = $this->baseQuery($companyId)->where('b.id', strtolower($booking))->first();
        abort_unless($row !== null, 404, 'Booking not found.');

        $guests = DB::table('rt_booking_guests')
            ->where('booking_id', $row->id)
            ->orderByDesc('is_lead')
            ->orderBy('room_index')
            ->orderBy('last_name')
            ->get(['first_name', 'last_name', 'guest_type', 'is_lead', 'room_index', 'age', 'nationality_code'])
            ->map(fn ($guest) => [
                'first_name' => $guest->first_name,
                'last_name' => $guest->last_name,
                'guest_type' => $guest->guest_type,
                'is_lead' => (bool) $guest->is_lead,
                'room_index' => $guest->room_index === null ? null : (int) $guest->room_index,
                'age' => $guest->age === null ? null : (int) $guest->age,
                'nationality_code' => $guest->nationality_code,
            ])
            ->values();

        return response()->json(['data' => $this->summary($row) + ['guests' => $guests]])
            ->header('Cache-Control', 'no-store');
    }

    private function baseQuery(string $companyId)
    {
        $latestSnapshot = DB::table('rt_booking_price_snapshots')
            ->selectRaw('DISTINCT ON (booking_id) booking_id, currency AS price_currency, customer_total')
            ->orderBy('booking_id')
            ->orderByDesc('version');

        return DB::table('rt_bookings as b')
            ->join('rt_orders as o', 'o.id', '=', 'b.order_id')
            ->leftJoin('rt_hotel_booking_details as h', 'h.booking_id', '=', 'b.id')
            ->leftJoin('rt_customers as c', function ($join) use ($companyId) {
                $join->on('c.id', '=', 'b.customer_id')->where('c.company_id', '=', $companyId);
            })
            ->leftJoinSub($latestSnapshot, 'p', 'p.booking_id', '=', 'b.id')
            ->where('b.company_id', $companyId)
            ->where('b.channel', 'b2b')
            ->select([
                'b.id', 'b.booking_no', 'o.order_no', 'b.service_type', 'b.status', 'b.currency',
                'b.created_at', 'b.updated_at',
                'h.check_in', 'h.check_out', 'h.rooms_count',
                'c.id as customer_id', 'c.first_name as customer_first_name', 'c.last_name as customer_last_name',
                'p.customer_total', 'p.price_currency',
            ]);
    }

    private function summary(object $row): array
    {
        return [
            'booking_id' => $row->id,
            'booking_no' => $row->booking_no,
            'order_no' => $row->order_no,
            'service_type' => $row->service_type,
            'status' => $row->status,
            'currency' => $row->price_currency ?? $row->currency,
            // Stored with extra scale; shown with two decimals, as quoted.
            'customer_total' => $row->customer_total === null ? null : bcadd((string) $row->customer_total, '0', 2),
            'check_in' => $row->check_in,
            'check_out' => $row->check_out,
            'rooms_count' => $row->rooms_count === null ? null : (int) $row->rooms_count,
            'customer' => $row->customer_id === null ? null : [
                'id' => $row->customer_id,
                'first_name' => $row->customer_first_name,
                'last_name' => $row->customer_last_name,
            ],
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
