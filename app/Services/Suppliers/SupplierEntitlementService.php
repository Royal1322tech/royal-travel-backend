<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SupplierEntitlementService
{
    public function isAllowed(
        string $supplierCode,
        string $channel,
        string $serviceType,
        ?string $productScope = null,
        ?string $rateScope = null
    ): bool {
        if (!in_array($channel, ['b2b', 'b2c'], true)) {
            throw new InvalidArgumentException('Invalid channel.');
        }

        if ($supplierCode === '' || $serviceType === '') {
            throw new InvalidArgumentException('Supplier and service are required.');
        }

        $now = now();

        $query = DB::table('rt_supplier_entitlements as e')
            ->join('rt_suppliers as s', 's.id', '=', 'e.supplier_id')
            ->where('s.code', $supplierCode)
            ->where('s.status', 'active')
            ->where('e.channel', $channel)
            ->where('e.service_type', $serviceType)
            ->where('e.is_allowed', true)
            ->where('e.is_verified', true)
            ->where('e.valid_from', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('e.valid_to')
                    ->orWhere('e.valid_to', '>', $now);
            });

        // NULL scope represents a general entitlement.
        // Scoped entitlements must match the requested scope.
        if ($productScope === null) {
            $query->whereNull('e.product_scope');
        } else {
            $query->where(function ($q) use ($productScope) {
                $q->whereNull('e.product_scope')
                    ->orWhere('e.product_scope', $productScope);
            });
        }

        if ($rateScope === null) {
            $query->whereNull('e.rate_scope');
        } else {
            $query->where(function ($q) use ($rateScope) {
                $q->whereNull('e.rate_scope')
                    ->orWhere('e.rate_scope', $rateScope);
            });
        }

        return $query->exists();
    }
}
