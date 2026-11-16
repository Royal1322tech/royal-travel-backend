<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2BHotelRoomsTest extends TestCase
{
    public function test_rooms_requires_authentication(): void
    {
        $response = $this->postJson(
            '/api/v1/b2b/hotels/8675779/rooms',
            [
                'checkin' => '2026-12-10',
                'checkout' => '2026-12-12',
                'residency' => 'eg',
                'language' => 'en',
                'currency' => 'USD',
                'guests' => [
                    [
                        'adults' => 2,
                        'children' => [],
                    ],
                ],
            ]
        );

        $response->assertStatus(401);
    }

    public function test_rooms_controller_is_registered(): void
    {
        $route = app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create(
                '/api/v1/b2b/hotels/8675779/rooms',
                'POST'
            )
        );

        $this->assertSame(
            \App\Http\Controllers\Api\V1\B2B\Hotels\HotelRoomsApiController::class
                . '@rooms',
            $route->getActionName()
        );
    }
}
