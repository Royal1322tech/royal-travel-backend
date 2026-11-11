<?php

namespace Tests\Feature\Hotels;

use App\Http\Controllers\Admin\HotelRoomsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class HotelRoomsSecurityTest extends TestCase
{
    private function requestWithSession(): Request
    {
        $request = Request::create(
            '/admin/hotels/rooms/test',
            'GET'
        );

        $request->setLaravelSession(
            app('session')->driver()
        );

        return $request;
    }

    private function cacheKey(string $token): string
    {
        return 'admin_hotel_rooms:' . hash('sha256', $token);
    }

    private function validSnapshot(Request $request): array
    {
        return [
            'session' => hash(
                'sha256',
                $request->session()->getId()
            ),
            'environment' => config(
                'services.ratehawk.environment',
                'sandbox'
            ),
            'hid' => 123456,
            'hotel' => [
                'name' => 'Test Hotel',
            ],
            'search' => [
                'checkin' => '2027-03-10',
                'checkout' => '2027-03-12',
                'residency' => 'eg',
                'currency' => 'USD',
                'language' => 'en',
                'guests' => [
                    [
                        'adults' => 2,
                        'children' => [],
                    ],
                ],
            ],
            'rates_source' => 'hotelpage',
            'rates' => [
                [
                    'room' => [
                        'name' => 'Deluxe',
                    ],
                    'meal' => [
                        'name' => 'Breakfast',
                    ],
                    'availability' => [
                        'allotment' => 2,
                    ],
                    'supplier' => [
                        'book_hash' => 'h-PRIVATE_BOOK_HASH',
                        'match_hash' => 'PRIVATE_HASH',
                        'supplier_amount' => '100.00',
                        'commission_info' => [
                            'amount' => '15.00',
                        ],
                        'is_package' => false,
                    ],
                ],
            ],
        ];
    }

    private function showRooms(
        Request $request,
        string $token
    ) {
        return app()->call(
            [app(HotelRoomsController::class), 'show'],
            [
                'request' => $request,
                'token' => $token,
            ]
        );
    }

    public function test_invalid_token_is_rejected(): void
    {
        try {
            $this->showRooms(
                $this->requestWithSession(),
                '../invalid'
            );

            $this->fail('Invalid token should be rejected.');
        } catch (NotFoundHttpException $exception) {
            $this->assertSame(
                404,
                $exception->getStatusCode()
            );
        }
    }

    public function test_expired_snapshot_is_rejected(): void
    {
        try {
            $this->showRooms(
                $this->requestWithSession(),
                Str::random(48)
            );

            $this->fail('Expired snapshot should be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(
                410,
                $exception->getStatusCode()
            );
        }
    }

    public function test_different_session_is_rejected(): void
    {
        $request = $this->requestWithSession();
        $token = Str::random(48);
        $key = $this->cacheKey($token);

        $snapshot = $this->validSnapshot($request);

        $snapshot['session'] = hash(
            'sha256',
            'another-session'
        );

        Cache::put($key, $snapshot, 60);

        try {
            $this->showRooms($request, $token);

            $this->fail('Different session should be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode()
            );
        } finally {
            Cache::forget($key);
        }
    }

    public function test_supplier_pricing_is_not_passed_to_view(): void
    {
        $request = $this->requestWithSession();
        $token = Str::random(48);
        $key = $this->cacheKey($token);

        Cache::put(
            $key,
            $this->validSnapshot($request),
            60
        );

        try {
            $view = $this->showRooms(
                $request,
                $token
            );

            $data = $view->getData();

            $roomsJson = json_encode(
                $data['rooms']
            );

            $this->assertSame(
                'Deluxe',
                $data['rooms'][0]['name']
            );

            $this->assertStringNotContainsString(
                '100.00',
                $roomsJson
            );

            $this->assertStringNotContainsString(
                '15.00',
                $roomsJson
            );

            $this->assertStringNotContainsString(
                'PRIVATE_HASH',
                $roomsJson
            );

            $this->assertStringNotContainsString(
                'h-PRIVATE_BOOK_HASH',
                $roomsJson
            );

            $this->assertArrayNotHasKey(
                'supplier',
                $data['rooms'][0]
            );
        } finally {
            Cache::forget($key);
        }
    }

    public function test_valid_room_selection_is_accepted_without_booking(): void
    {
        $request = $this->requestWithSession();
        $request->setMethod('POST');
        $request->request->set('rate_index', 0);

        $token = Str::random(48);
        $key = $this->cacheKey($token);

        Cache::put(
            $key,
            $this->validSnapshot($request),
            60
        );

        try {
            $response = app(
                HotelRoomsController::class
            )->select($request, $token);

            $this->assertSame(
                200,
                $response->getStatusCode()
            );

            $data = $response->getData(true);

            $this->assertSame(
                'selected',
                $data['status']
            );

            $this->assertSame(
                0,
                $data['rate_index']
            );

            $this->assertFalse(
                $data['booking_enabled']
            );

            $this->assertStringNotContainsString(
                'PRIVATE_HASH',
                $response->getContent()
            );

            $this->assertStringNotContainsString(
                'h-PRIVATE_BOOK_HASH',
                $response->getContent()
            );

            $this->assertStringNotContainsString(
                '100.00',
                $response->getContent()
            );
        } finally {
            Cache::forget($key);
        }
    }

    public function test_nonexistent_rate_index_is_rejected(): void
    {
        $request = $this->requestWithSession();
        $request->setMethod('POST');
        $request->request->set('rate_index', 99);

        $token = Str::random(48);
        $key = $this->cacheKey($token);

        Cache::put(
            $key,
            $this->validSnapshot($request),
            60
        );

        try {
            app(HotelRoomsController::class)
                ->select($request, $token);

            $this->fail('Invalid rate index should be rejected.');
        } catch (NotFoundHttpException $exception) {
            $this->assertSame(
                404,
                $exception->getStatusCode()
            );
        } finally {
            Cache::forget($key);
        }
    }

    public function test_expired_room_selection_is_rejected(): void
    {
        $request = $this->requestWithSession();
        $request->setMethod('POST');
        $request->request->set('rate_index', 0);

        try {
            app(HotelRoomsController::class)
                ->select($request, Str::random(48));

            $this->fail('Expired selection should be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(
                410,
                $exception->getStatusCode()
            );
        }
    }

    public function test_room_selection_from_another_session_is_rejected(): void
    {
        $request = $this->requestWithSession();
        $request->setMethod('POST');
        $request->request->set('rate_index', 0);

        $token = Str::random(48);
        $key = $this->cacheKey($token);

        $snapshot = $this->validSnapshot($request);

        $snapshot['session'] = hash(
            'sha256',
            'another-session'
        );

        Cache::put($key, $snapshot, 60);

        try {
            app(HotelRoomsController::class)
                ->select($request, $token);

            $this->fail('Different session should be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode()
            );
        } finally {
            Cache::forget($key);
        }
    }

    public function test_serp_rates_cannot_be_selected(): void
    {
        $request = $this->requestWithSession();
        $request->setMethod('POST');
        $request->request->set('rate_index', 0);

        $token = Str::random(48);
        $key = $this->cacheKey($token);

        $snapshot = $this->validSnapshot($request);
        $snapshot['rates_source'] = 'serp';

        Cache::put($key, $snapshot, 60);

        try {
            app(HotelRoomsController::class)
                ->select($request, $token);

            $this->fail('SERP rates should not be selectable.');
        } catch (HttpException $exception) {
            $this->assertSame(
                409,
                $exception->getStatusCode()
            );
        } finally {
            Cache::forget($key);
        }
    }

    public function test_invalid_hotelpage_book_hash_is_rejected(): void
    {
        $request = $this->requestWithSession();
        $request->setMethod('POST');
        $request->request->set('rate_index', 0);

        $token = Str::random(48);
        $key = $this->cacheKey($token);

        $snapshot = $this->validSnapshot($request);

        $snapshot['rates'][0]['supplier']['book_hash'] =
            'invalid-hash';

        Cache::put($key, $snapshot, 60);

        try {
            app(HotelRoomsController::class)
                ->select($request, $token);

            $this->fail('Invalid Hotelpage hash should be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(
                409,
                $exception->getStatusCode()
            );
        } finally {
            Cache::forget($key);
        }
    }

    public function test_environment_mismatch_is_rejected(): void
    {
        $request = $this->requestWithSession();
        $request->setMethod('POST');
        $request->request->set('rate_index', 0);

        $token = Str::random(48);
        $key = $this->cacheKey($token);

        $snapshot = $this->validSnapshot($request);

        $snapshot['environment'] = 'different-environment';

        Cache::put($key, $snapshot, 60);

        try {
            app(HotelRoomsController::class)
                ->select($request, $token);

            $this->fail('Environment mismatch should be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(
                410,
                $exception->getStatusCode()
            );
        } finally {
            Cache::forget($key);
        }
    }
}
