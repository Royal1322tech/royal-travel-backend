<?php
namespace App\Services\Pricing;

final class HotelCurrencyQuoteService
{
    public function __construct(
        private readonly HotelPricingService $pricing,
        private readonly FxConversionService $fx,
    ) {}

    /**
     * Internal use only. Call ONLY after supplier total is independently verified.
     * Do not expose policy IDs or internal FX details to public clients.
     */
    public function quoteVerifiedUsd(
        string $verifiedSupplierTotalUsd,
        string $channel,
        string $displayCurrency,
        ?string $companyId = null
    ): array {
        $usd = $this->pricing->quoteUsd($verifiedSupplierTotalUsd,$channel,$companyId);
        $selling = $this->fx->convertUsd($usd['selling_price'],$displayCurrency);
        $public = [
            'currency'=>$selling['currency'],
            'customer_selling_price'=>$selling['amount'],
        ];
        if ($channel === 'b2b') {
            $purchase = $this->fx->convertUsd($usd['company_buy_price'],$displayCurrency);
            $public['agency_purchase_price'] = $purchase['amount'];
        }
        return $public;
    }
}
