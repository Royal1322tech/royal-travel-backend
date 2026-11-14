<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RateHawkB2BEntitlementSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            $supplier = DB::table('rt_suppliers')
                ->where('code', 'RATEHAWK')
                ->first();

            if (! $supplier) {
                $supplierId = (string) \Illuminate\Support\Str::uuid();
                DB::table('rt_suppliers')->insert([
                    'id' => $supplierId,
                    'code' => 'RATEHAWK',
                    'name' => 'RateHawk',
                    'status' => 'active',
                    'adapter_code' => 'RATEHAWK',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $supplierId = $supplier->id;
            }

            $existing = DB::table('rt_supplier_entitlements')
                ->where('supplier_id', $supplierId)
                ->where('channel', 'b2b')
                ->where('service_type', 'hotel')
                ->whereNull('product_scope')
                ->whereNull('rate_scope')
                ->first();

            if (! $existing) {
                DB::table('rt_supplier_entitlements')->insert([
                    'supplier_id' => $supplierId,
                    'channel' => 'b2b',
                    'service_type' => 'hotel',
                    'product_scope' => null,
                    'rate_scope' => null,
                    'is_allowed' => true,
                    'is_verified' => true,
                    'valid_from' => $now,
                    'valid_to' => null,
                    'version' => 1,
                    'created_at' => $now,
                ]);
            }
        });
    }
}
