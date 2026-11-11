<?php

namespace App\Services\Pricing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/** USD-only initial implementation. Never return supplier cost to B2B clients. */
final class HotelPricingService
{
    public const DIRECT = 'royal-direct-hotels';

    public function policyCode(string $level, ?string $companyId = null): string
    {
        return match ($level) {
            'royal_direct' => self::DIRECT,
            'royal_b2b' => 'royal-b2b-hotels-' . $this->uuid($companyId),
            'company_b2b' => 'company-b2b-hotels-' . $this->uuid($companyId),
            default => throw new InvalidArgumentException('Invalid pricing level.'),
        };
    }

    private function uuid(?string $value): string
    {
        if (!$value || !Str::isUuid($value)) {
            throw new InvalidArgumentException('Valid company UUID is required.');
        }
        return strtolower($value);
    }

    public function activeRule(string $level, ?string $companyId = null): ?array
    {
        $code = $this->policyCode($level, $companyId);
        $policy = DB::table('rt_pricing_policies')
            ->where('code', $code)->where('status', 'active')
            ->where(function ($q) { $q->whereNull('effective_at')->orWhere('effective_at', '<=', now()); })
            ->orderByDesc('version')->first();
        if (!$policy) return null;
        $rules = DB::table('rt_pricing_rules')->where('policy_id', $policy->id)->get();
        $values = ['percentage' => '0', 'fixed' => '0'];
        foreach ($rules as $rule) {
            if ($rule->rule_kind === 'hotel_markup' && in_array($rule->calculation_type, ['percentage', 'fixed'], true)) {
                $values[$rule->calculation_type] = (string) $rule->value;
            }
        }
        return ['policy_id' => $policy->id, 'version' => $policy->version] + $values;
    }

    /**
     * Persist a new version. B2B dashboard must supply expectedVersion and actorUserId.
     * Optional parameters preserve existing internal/Admin callers; migrate them separately.
     * Returns new policy ID and version for the controller, never for a public API.
     */
    public function saveRule(
        string $level,
        ?string $companyId,
        string $percentage,
        string $fixed,
        ?int $expectedVersion = null,
        ?int $actorUserId = null,
        ?string $requestId = null
    ): array {
        $code = $this->policyCode($level, $companyId);
        if (!preg_match('/^\d{1,3}(?:\.\d{1,6})?$/D', $percentage)
            || !preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $fixed)) {
            throw new InvalidArgumentException('Invalid markup format.');
        }
        if ((float) $percentage > 100 || (float) $fixed > 1000000) {
            throw new InvalidArgumentException('Markup exceeds configured safety limit.');
        }
        if ($expectedVersion !== null && $expectedVersion < 0) {
            throw new InvalidArgumentException('Invalid expected pricing version.');
        }

        return DB::transaction(function () use (
            $code, $level, $companyId, $percentage, $fixed,
            $expectedVersion, $actorUserId, $requestId
        ): array {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$code]);
            $current = DB::table('rt_pricing_policies')
                ->where('code', $code)->where('status', 'active')
                ->orderByDesc('version')->first();
            $currentVersion = $current ? (int) $current->version : 0;
            if ($expectedVersion !== null && $currentVersion !== $expectedVersion) {
                throw new \App\Services\Pricing\PricingVersionConflictException(
                    'Pricing settings changed. Refresh the page and try again.'
                );
            }
            $version = ((int) DB::table('rt_pricing_policies')
                ->where('code', $code)->max('version')) + 1;
            DB::table('rt_pricing_policies')->where('code', $code)
                ->where('status', 'active')->update(['status' => 'superseded']);
            $policyId = (string) Str::uuid();
            DB::table('rt_pricing_policies')->insert([
                'id' => $policyId, 'code' => $code, 'version' => $version,
                'channel' => $level === 'royal_direct' ? 'b2c' : 'b2b',
                'company_id' => $companyId, 'status' => 'active',
                'effective_at' => now(), 'created_at' => now(),
            ]);
            foreach (['percentage' => $percentage, 'fixed' => $fixed] as $type => $value) {
                DB::table('rt_pricing_rules')->insert([
                    'id' => (string) Str::uuid(), 'policy_id' => $policyId,
                    'rule_kind' => 'hotel_markup', 'calculation_type' => $type,
                    'value' => $value, 'priority' => 0, 'service_type' => 'hotel',
                    'supplier_id' => null,
                ]);
            }
            if ($level === 'company_b2b' && $actorUserId !== null) {
                DB::table('rt_audit_events')->insert([
                    'id' => (string) Str::uuid(),
                    'actor_user_id' => $actorUserId,
                    'company_id' => $companyId,
                    'action' => 'pricing.company.updated',
                    'entity_type' => 'pricing_policy',
                    'entity_id' => $policyId,
                    'request_id' => $requestId,
                    'occurred_at' => now(),
                    'metadata' => json_encode([
                        'previous_version' => $currentVersion,
                        'new_version' => $version,
                        'percentage' => $percentage,
                        'fixed' => $fixed,
                    ], JSON_THROW_ON_ERROR),
                ]);
            }
            return ['policy_id' => $policyId, 'version' => $version];
        });
    }

    /** Input is a supplier USD total in dollars, not a display price or nightly rate. */
    public function quoteUsd(string $supplierTotal, string $channel, ?string $companyId = null): array
    {
        $cost = $this->toCents($supplierTotal);
        if ($channel === 'b2c') {
            $royal = $this->activeRule('royal_direct');
            if (!$royal) throw new RuntimeException('Royal direct pricing is not configured.');
            $direct = $this->apply($cost, $royal);
            return ['currency' => 'USD', 'selling_price' => $this->dollars($direct),
                'royal_policy_id' => $royal['policy_id']];
        }
        if ($channel !== 'b2b') throw new InvalidArgumentException('Unsupported channel.');
        $this->uuid($companyId);
        $royal = $this->activeRule('royal_b2b', $companyId);
        $company = $this->activeRule('company_b2b', $companyId);
        if (!$royal || !$company) throw new RuntimeException('Company pricing is not configured.');
        $wholesale = $this->apply($cost, $royal);
        $retail = $this->apply($wholesale, $company);
        return ['currency' => 'USD', 'selling_price' => $this->dollars($retail),
            'company_buy_price' => $this->dollars($wholesale),
            'royal_policy_id' => $royal['policy_id'], 'company_policy_id' => $company['policy_id']];
    }

    private function toCents(string $amount): int
    {
        if (!preg_match('/^(0|[1-9]\d{0,8})(?:\.(\d{1,2}))?$/D', $amount, $m)) {
            throw new InvalidArgumentException('Expected nonnegative USD amount with up to two decimals.');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function apply(int $base, array $rule): int
    {
        // Percentage is stored to 6 decimal places: 5.000000% => 5,000,000 units.
        $parts = explode('.', (string) $rule['percentage'], 2);
        $percentUnits = (int) $parts[0] * 1000000 + (int) str_pad($parts[1] ?? '', 6, '0');
        $percentCents = intdiv($base * $percentUnits + 50000000, 100000000);
        return $base + $percentCents + $this->toCents($this->normalizeStoredFixed((string) $rule['fixed']));
    }

    private function normalizeStoredFixed(string $value): string
    {
        if (!str_contains($value, '.')) return $value;
        [$whole, $fraction] = explode('.', $value, 2);
        if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
            throw new InvalidArgumentException('Fixed USD markup cannot contain fractional cents.');
        }
        return $whole . '.' . substr(str_pad($fraction, 2, '0'), 0, 2);
    }

    private function dollars(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
