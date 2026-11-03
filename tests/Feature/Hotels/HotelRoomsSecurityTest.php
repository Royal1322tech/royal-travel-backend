<?php

namespace Tests\Feature\Hotels;

use App\Http\Controllers\Admin\HotelRoomsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HotelRoomsSecurityTest extends TestCase
{
    private function requestWithSession(): Request
    {
        $request = Request::create('/admin/hotels/rooms/test', 'GET');
        $request->setLaravelSession(app('session')->driver());

        return $request;
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);

        app(HotelRoomsController::class)->show(
            $this->requestWithSession(),
            '../invalid'
        );
    }

    public function test_expired_snapshot_is_rejected(): void
    {
        $this->expectException(HttpException::class);

        app(HotelRoomsController::class)->show(
            $this->requestWithSession(),
            Str::random(48)
        );
    }

    public function test_different_session_is_rejected(): void
    {
        $token = Str::random(48);

        Cache::put('admin_hotel_rooms:' . hash('sha256', $token), [
            'session' => hash('sha256', 'another-session'),
            'hotel' => [],
            'search' => [],
            'rates' => [],
        ], 60);

        try {
            app(HotelRoomsController::class)->show(
                $this->requestWithSession(),
                $token
            );

            $this->fail('Different session should be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        } finally {
            Cache::forget('admin_hotel_rooms:' . hash('sha256', $token));
        }
    }

    public function test_supplier_pricing_is_not_passed_to_view(): void
    {
        $request = $this->requestWithSession();
        $token = Str::random(48);

        Cache::put('admin_hotel_rooms:' . hash('sha256', $token), [
            'session' => hash('sha256', $request->session()->getId()),
            'hotel' => ['name' => 'Test Hotel'],
            'search' => ['checkin' => '2027-03-10'],
            'rates' => [[
                'room' => ['name' => 'Deluxe'],
                'meal' => ['name' => 'Breakfast'],
                'availability' => ['allotment' => 2],
                'supplier' => [
                    'supplier_amount' => '100.00',
                    'commission_info' => ['amount' => '15.00'],
                    'match_hash' => 'PRIVATE_HASH',
                    'is_package' => false,
                ],
            ]],
        ], 60);

        try {
            $view = app(HotelRoomsController::class)->show(
                $request,
                $token
            );

            $data = $view->getData();
            $roomsJson = json_encode($data['rooms']);

            $this->assertSame('Deluxe', $data['rooms'][0]['name']);
            $this->assertStringNotContainsString('100.00', $roomsJson);
            $this->assertStringNotContainsString('15.00', $roomsJson);
            $this->assertStringNotContainsString('PRIVATE_HASH', $roomsJson);
            $this->assertArrayNotHasKey('supplier', $data['rooms'][0]);
        } finally {
            Cache::forget('admin_hotel_rooms:' . hash('sha256', $token));
        }
    }
}
