<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HotelRoomsController extends Controller
{
    public function show(Request $request, string $token)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{48}$/', $token) === 1, 404);
        $snapshot = Cache::get('admin_hotel_rooms:' . hash('sha256', $token));
        abort_unless(is_array($snapshot), 410, 'Room search expired. Please search again.');
        abort_unless(hash_equals(
            (string) ($snapshot['session'] ?? ''),
            hash('sha256', $request->session()->getId())
        ), 403);

        $rooms = [];
        foreach (($snapshot['rates'] ?? []) as $rate) {
            if (!is_array($rate)) continue;
            $room = $rate['room'] ?? [];
            $meal = $rate['meal'] ?? [];
            $availability = $rate['availability'] ?? [];
            $rooms[] = [
                'name' => $room['name'] ?? 'Room details pending',
                'main_name' => $room['main_name'] ?? null,
                'bedding_type' => $room['bedding_type'] ?? null,
                'bathroom' => is_string($room['bathroom'] ?? null) ? $room['bathroom'] : null,
                'meal' => $meal['name'] ?? $meal['code'] ?? 'Meal details pending',
                'breakfast' => $meal['has_breakfast'] ?? null,
                'allotment' => $availability['allotment'] ?? null,
                'package' => $rate['supplier']['is_package'] ?? null,
            ];
        }

        return view('admin.hotels.rooms', [
            'hotel' => $snapshot['hotel'],
            'search' => $snapshot['search'],
            'rooms' => $rooms,
        ]);
    }
}
