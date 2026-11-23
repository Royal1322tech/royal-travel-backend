<?php

namespace Tests\Feature\Hotels;

use App\Services\Hotels\HotelSearchApplicationService;
use App\Services\Hotels\RateHawkHotelContentService;
use App\Services\Hotels\RateHawkSearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/** Content-dependent filters (stars) give the same complete result in every language, or a retryable error. */
final class HotelSearchFilterStabilityTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int, array<string, mixed>> hid => content */
    private array $content = [];
    /** @var list<string> language of each content lookup */
    private array $lookups = [];
    private ?int $failingHid = null;

    protected function setUp(): void
    {
        parent::setUp();
        $supplier = DB::table('rt_suppliers')->where('code', 'RATEHAWK')->value('id');
        if (!$supplier) {
            $supplier = (string) Str::uuid();
            DB::table('rt_suppliers')->insert(['id' => $supplier, 'code' => 'RATEHAWK', 'name' => 'RateHawk', 'status' => 'active']);
        }
        DB::table('rt_supplier_entitlements')->insert([
            'supplier_id' => $supplier, 'channel' => 'b2b', 'service_type' => 'hotel',
            'is_allowed' => true, 'is_verified' => true, 'valid_from' => now()->subDay(),
        ]);

        $hotels = [];
        foreach ([101 => 5, 102 => 3, 103 => 5, 104 => 4] as $hid => $stars) {
            $hotels[] = ['hid' => $hid, 'rates' => [['room_name' => 'Room', 'meal' => 'nomeal', 'book_hash' => 'h-' . $hid, 'payment_options' => ['payment_types' => []]]]];
            $this->content[$hid] = ['name' => "Hotel {$hid}", 'address' => 'Dubai', 'star_rating' => $stars, 'amenity_groups' => []];
        }
        $this->app->instance(RateHawkSearchService::class, new class ($hotels) extends RateHawkSearchService {
            public function __construct(private readonly array $hotels) {}
            public function searchByGeo(array $params): array { return ['hotels' => $this->hotels]; }
        });
        $test = $this;
        $this->app->instance(RateHawkHotelContentService::class, new class ($test) extends RateHawkHotelContentService {
            public function __construct(private readonly object $test) {}
            public function getHotel(string|int $hid, string $language = 'en'): array { return $this->test->content((int) $hid, $language); }
        });
    }

    public function content(int $hid, string $language): array
    {
        $this->lookups[] = $language;
        if ($hid === $this->failingHid) {
            throw new RuntimeException('RateHawk hotel content request failed.');
        }

        return ['name' => ($language === 'ar' ? 'فندق ' : 'Hotel ') . $hid] + $this->content[$hid];
    }

    private function search(string $language): array
    {
        return app(HotelSearchApplicationService::class)->search([
            'checkin' => now()->addDays(10)->toDateString(), 'checkout' => now()->addDays(12)->toDateString(),
            'latitude' => 25.2, 'longitude' => 55.27, 'radius' => 10000, 'residency' => 'eg', 'language' => $language,
            'currency' => 'USD', 'guests' => [['adults' => 2, 'children' => []]],
            'filters' => ['stars' => [5]], 'sort' => 'recommended', 'page' => 1, 'per_page' => 20,
        ], 'b2b', (string) Str::uuid());
    }

    public function test_star_filter_is_identical_in_every_language_and_shown_in_the_requested_one(): void
    {
        $en = $this->search('en');
        $ar = $this->search('ar');

        $this->assertSame(2, $en['data']['total']);
        $this->assertSame(2, $ar['data']['total']);
        $this->assertSame([101, 103], array_column($en['data']['hotels'], 'hid'));
        $this->assertSame([101, 103], array_column($ar['data']['hotels'], 'hid'));
        $this->assertSame('فندق 101', $ar['data']['hotels'][0]['name']);
    }

    public function test_a_hotel_whose_details_fail_is_never_dropped_silently(): void
    {
        $this->failingHid = 103;
        $result = $this->search('ar');

        $this->assertFalse($result['success']);
        $this->assertSame('HOTEL_SEARCH_NOT_READY', $result['code']);
        $this->assertSame(0, $result['data']['total']);
    }
}
