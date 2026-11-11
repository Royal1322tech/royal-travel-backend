<?php

namespace Tests\Feature\Hotels;

use App\Http\Controllers\Admin\HotelRoomsController;
use App\Services\Hotels\RateHawkPrebookService;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class HotelRoomsPrebookTest extends TestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('services.ratehawk.environment', 'sandbox');
        Cache::flush();
        $this->token = str_repeat('A', 48);
    }

    private function request(string $sessionId = 'test-session'): Request
    {
        $request = Request::create(
            '/admin/hotels/rooms/' . $this->token . '/prebook',
            'POST',
            ['rate_index' => 0]
        );

        $session = clone app('session')->driver();
        $session->setId($sessionId);
        $request->setLaravelSession($session);

        return $request;
    }

    private function snapshot(Request $request): array
    {
        return [
            'session' => hash('sha256', $request->session()->getId()),
            'environment' => 'sandbox',
            'hid' => 123456,
            'rates_source' => 'hotelpage',
            'rates' => [
                [
                    'supplier' => [
                        'book_hash' => 'h-PRIVATE_HOTELPAGE_HASH',
                    ],
                ],
            ],
        ];
    }

    private function storeSnapshot(array $snapshot): void
    {
        Cache::put(
            'admin_hotel_rooms:' . hash('sha256', $this->token),
            $snapshot,
            now()->addMinutes(15)
        );
    }

    private function service(bool $expectCall = false): RateHawkPrebookService
    {
        $client = Mockery::mock(RateHawkClient::class);

        if ($expectCall) {
            $client->shouldReceive('post')
                ->once()
                ->with(
                    '/api/b2b/v3/hotel/prebook/',
                    [
                        'hash' => 'h-PRIVATE_HOTELPAGE_HASH',
                        'price_increase_percent' => 0,
                    ],
                    60
                )
                ->andReturn([
                    'status' => 'ok',
                    'data' => [
                        'changes' => ['price_changed' => false],
                        'hotels' => [
                            [
                                'hid' => 123456,
                                'rates' => [
                                    [
                                        'book_hash' => 'p-PRIVATE_PREBOOK_HASH',
                                        'payment_options' => [
                                            'payment_types' => [
                                                ['type' => 'now'],
                                            ],
                                        ],
                                        'supplier_cost' => 'SECRET_COST_999',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
        } else {
            $client->shouldNotReceive('post');
        }

        return new RateHawkPrebookService($client);
    }

    private function callPrebook(Request $request, RateHawkPrebookService $service)
    {
        return app()->call(
            [app(HotelRoomsController::class), 'prebook'],
            [
                'request' => $request,
                'token' => $this->token,
                'prebookService' => $service,
            ]
        );
    }

    private function assertHttpStatus(int $expected, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected HTTP {$expected}.");
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame($expected, $exception->getStatusCode());
        }
    }

    public function test_successful_prebook_does_not_expose_supplier_data(): void
    {
        $request = $this->request();
        $this->storeSnapshot($this->snapshot($request));

        $response = $this->callPrebook($request, $this->service(true));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('rechecked', $data['status']);
        $this->assertFalse($data['price_changed']);
        $this->assertTrue($data['review_required']);
        $this->assertFalse($data['booking_enabled']);

        $content = $response->getContent();
        $this->assertStringNotContainsString('p-PRIVATE_PREBOOK_HASH', $content);
        $this->assertStringNotContainsString('SECRET_COST_999', $content);
        $this->assertStringNotContainsString('h-PRIVATE_HOTELPAGE_HASH', $content);
    }

    public function test_expired_search_is_rejected(): void
    {
        $request = $this->request();
        $this->assertHttpStatus(410, fn () => $this->callPrebook($request, $this->service()));
    }

    public function test_another_session_is_rejected(): void
    {
        $originalRequest = $this->request('original-session');
        $this->storeSnapshot($this->snapshot($originalRequest));

        $differentRequest = $this->request('different-session');
        $this->assertNotSame(
            $originalRequest->session()->getId(),
            $differentRequest->session()->getId()
        );
        $this->assertHttpStatus(403, fn () => $this->callPrebook($differentRequest, $this->service()));
    }

    public function test_environment_mismatch_is_rejected(): void
    {
        $request = $this->request();
        $snapshot = $this->snapshot($request);
        $snapshot['environment'] = 'production';
        $this->storeSnapshot($snapshot);

        $this->assertHttpStatus(410, fn () => $this->callPrebook($request, $this->service()));
    }

    public function test_serp_rates_are_rejected(): void
    {
        $request = $this->request();
        $snapshot = $this->snapshot($request);
        $snapshot['rates_source'] = 'serp';
        $this->storeSnapshot($snapshot);

        $this->assertHttpStatus(409, fn () => $this->callPrebook($request, $this->service()));
    }

    public function test_invalid_book_hash_is_rejected(): void
    {
        $request = $this->request();
        $snapshot = $this->snapshot($request);
        $snapshot['rates'][0]['supplier']['book_hash'] = 'invalid';
        $this->storeSnapshot($snapshot);

        $this->assertHttpStatus(409, fn () => $this->callPrebook($request, $this->service()));
    }

    public function test_prebook_route_requires_hotel_permission(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.hotels.rooms.prebook');
        $this->assertNotNull($route);
        $this->assertContains('admin.permission:hotels.view', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
    }
}
