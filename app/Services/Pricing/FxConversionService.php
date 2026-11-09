<?php
namespace App\Services\Pricing;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class FxConversionService
{
    public function convertUsd(string $amountUsd, string $targetCurrency): array
    {
        $targetCurrency = strtoupper(trim($targetCurrency));
        if (!in_array($targetCurrency, ['USD','EGP','SAR'], true)) {
            throw new InvalidArgumentException('Unsupported display currency.');
        }
        if (!preg_match('/^(0|[1-9]\\d{0,8})(?:\\.(\\d{1,2}))?$/D', $amountUsd)) {
            throw new InvalidArgumentException('Invalid USD price.');
        }
        if ($targetCurrency === 'USD') {
            return ['currency'=>'USD','amount'=>bcdiv(bcadd($amountUsd, '0.005', 3), '1', 2),
                'reference_rate'=>'1','effective_rate'=>'1','fx_adjustment_type'=>null];
        }

        $rate = DB::table('rt_fx_rates')
            ->where('base_currency','USD')->where('quote_currency',$targetCurrency)
            ->where('rate_type','reference')->where('effective_at','<=',now())
            ->where('expires_at','>',now())->orderByDesc('effective_at')->orderByDesc('recorded_at')->first();
        if (!$rate) {
            throw new RuntimeException('A current exchange rate is unavailable.');
        }
        $reference = (string)$rate->rate;
        $adjustment = DB::table('rt_fx_adjustments')
            ->where('base_currency','USD')->where('quote_currency',$targetCurrency)
            ->where('is_enabled',true)->first();
        $type = $adjustment ? (string)$adjustment->adjustment_type : null;
        $value = $adjustment ? (string)$adjustment->adjustment_value : '0';
        if ($type !== null && !in_array($type,['fixed','percentage'],true)) {
            throw new RuntimeException('Unsupported FX adjustment.');
        }
        // A fixed adjustment is in quote-currency units per 1 USD.
        // A percentage adjustment increases the reference FX rate.
        if (!preg_match('/^(0|[1-9]\d{0,11})(?:\.\d{1,12})?$/D', $reference)
            || !preg_match('/^(0|[1-9]\d{0,11})(?:\.\d{1,12})?$/D', $value)) {
            throw new RuntimeException('Invalid FX configuration.');
        }
        $effective = match($type) {
            'fixed' => bcadd($reference,$value,12),
            'percentage' => bcadd($reference,bcdiv(bcmul($reference,$value,12),'100',12),12),
            default => $reference
        };
        if (bccomp($effective,'0',12) <= 0) {
            throw new RuntimeException('Invalid commercial FX rate.');
        }
        // Round once, half up, to the supported currencies' 2 minor units.
        $raw = bcmul($amountUsd,$effective,12);
        $rounded = bcdiv(bcadd($raw,'0.005',12),'1',2);
        return [
            'currency'=>$targetCurrency,'amount'=>$rounded,
            'reference_rate'=>$reference,'effective_rate'=>$effective,
            'fx_adjustment_type'=>$type,
            'fx_effective_at'=>(string)$rate->effective_at,
        ];
    }
}
