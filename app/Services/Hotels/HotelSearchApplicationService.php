<?php

namespace App\Services\Hotels;

use App\Services\Suppliers\SupplierEntitlementService;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class HotelSearchApplicationService
{
    public function __construct(
        private readonly SupplierEntitlementService $entitlements,
        private readonly RateHawkSearchService $rateHawk,
        private readonly RateHawkRateNormalizer $normalizer,
        private readonly RateHawkHotelContentService $content,
        private readonly RateHawkPublicHotelProjector $projector,
    ) {}

    public function search(array $params, string $channel, ?string $companyId = null): array
    {
        if (!in_array($channel, ['b2b', 'b2c'], true)) {
            throw new InvalidArgumentException('Unsupported channel.');
        }

        if ($channel === 'b2b' && (!is_string($companyId) || !Str::isUuid($companyId))) {
            throw new InvalidArgumentException('Authenticated company is required.');
        }

        // Display currency is independent of the supplier settlement currency.
        $displayCurrency = strtoupper(trim((string) ($params['currency'] ?? 'USD')));
        if (!in_array($displayCurrency, ['USD', 'EGP', 'SAR'], true)) {
            throw new InvalidArgumentException('Unsupported display currency.');
        }

        if (!$this->entitlements->isAllowed('RATEHAWK', $channel, 'hotel')) {
            return $this->failure('HOTEL_SUPPLIER_UNAVAILABLE', 'Hotel search is currently unavailable.');
        }

        try {
            // Never send the user's display currency to RateHawk.
            // Prices remain unverified and must not be calculated from raw supplier fields.
            $supplierResult = $this->rateHawk->searchByGeo(array_merge($params, [
                'currency' => 'USD',
            ]));
        } catch (Throwable $exception) {
            report($exception);
            return $this->failure('HOTEL_SEARCH_NOT_READY', 'Hotel search is temporarily unavailable.');
        }

        // Pagination precedes content lookups. Never request content for the full SERP.
        $supplierHotels = array_values(array_filter(
            $supplierResult['hotels'] ?? [], 'is_array'
        ));
        $total = count($supplierHotels);
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(20, max(1, (int) ($params['per_page'] ?? 20)));
        $pageHotels = array_slice($supplierHotels, ($page - 1) * $perPage, $perPage);
        $hotels = [];

        foreach ($pageHotels as $offset => $hotel) {
            $rooms = [];
            foreach (($hotel['rates'] ?? []) as $rate) {
                if (!is_array($rate)) continue;
                try {
                    $rooms[] = $this->normalizer->publicPreview(
                        $this->normalizer->normalize($rate)
                    );
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            $content = [];
            $hid = $hotel['hid'] ?? null;
            // Enrich at most five hotels per page to bound external calls.
            if (count($hotels) < 5 && (is_int($hid) || is_string($hid))
                && ctype_digit((string) $hid)) {
                try {
                    $content = $this->content->getHotel(
                        $hid, (string) ($params['language'] ?? 'en')
                    );
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            $hotels[] = $this->projector->preview(
                $page * $perPage - $perPage + $offset,
                $rooms,
                $content
            );
        }

        return [
            'success' => true,
            'code' => 'HOTEL_SEARCH_PREVIEW',
            'message' => 'Hotel availability preview. Prices are not yet verified.',
            'data' => [
                'hotels' => $hotels,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'currency' => $displayCurrency,
                'supplier_currency' => 'USD',
                'pricing_verified' => false,
                'booking_enabled' => false,
            ],
        ];
    }

    private function failure(string $code, string $message): array
    {
        return [
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => ['hotels' => [], 'total' => 0],
        ];
    }
}
