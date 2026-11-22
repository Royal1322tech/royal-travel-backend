<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class B2BFinanceService {
   
public function snapshot(string $companyId): array
{
    $a = DB::table('rt_b2b_finance_accounts')
        ->where('company_id', $companyId)
        ->first();

    $wallet = (int) ($a->wallet_cents ?? 0);
    $reserved = (int) ($a->wallet_reserved_cents ?? 0);
    $walletAvailable = max(0, $wallet - $reserved);

    $limit = (int) ($a->credit_limit_cents ?? 0);
    $used = (int) ($a->credit_used_cents ?? 0);
    $frozen = (bool) ($a->credit_frozen ?? false);

    $creditAvailable = $frozen
        ? 0
        : max(0, $limit - $used);

    return [
        'currency' => 'USD',
        'wallet_cents' => $wallet,
        'wallet_reserved_cents' => $reserved,
        'wallet_available_cents' => $walletAvailable,
        'credit_limit_cents' => $limit,
        'credit_used_cents' => $used,
        'credit_available_cents' => $creditAvailable,
        'credit_frozen' => $frozen,
        'total_available_cents' => $walletAvailable + $creditAvailable,
    ];
}

    public function entries(string $companyId) {
        return DB::table('rt_b2b_finance_entries')->where('company_id',$companyId)
            ->orderByDesc('created_at')->orderByDesc('id')->limit(100)->get();
    }
    public function apply(string $companyId,string $actorId,array $data): void {
        DB::transaction(function () use ($companyId,$actorId,$data) {
            // Lock company even when the account has not been created yet.
            DB::table('rt_companies')->where('id',$companyId)->lockForUpdate()->firstOrFail();
            DB::table('rt_b2b_finance_accounts')->insertOrIgnore([
                'company_id'=>$companyId,'currency'=>'USD','wallet_cents'=>0,'credit_limit_cents'=>0,
                'credit_used_cents'=>0,'credit_frozen'=>false,'created_at'=>now(),'updated_at'=>now()
            ]);
            $a=DB::table('rt_b2b_finance_accounts')->where('company_id',$companyId)->lockForUpdate()->first();
            $reference=$data['reference'];
            if (DB::table('rt_b2b_finance_entries')->where('company_id',$companyId)->where('reference',$reference)->exists()) {
                throw ValidationException::withMessages(['reference'=>'This transaction reference has already been used.']);
            }
            $wallet=(int)$a->wallet_cents; $limit=(int)$a->credit_limit_cents;
            $used=(int)$a->credit_used_cents; $frozen=(bool)$a->credit_frozen;
            $amount=$data['amount_cents'] ?? null;
            switch($data['type']) {
                case 'wallet_topup': $wallet += $amount; break;
                case 'set_credit_limit': $limit = $amount; break;
                case 'credit_settlement':
                    if ($amount > $used) throw ValidationException::withMessages(['amount'=>'Payment exceeds outstanding debt.']);
                    $used -= $amount; break;
                case 'credit_freeze': $frozen=true; break;
                case 'credit_unfreeze': $frozen=false; break;
            }
            $reserved = (int) $a->wallet_reserved_cents;

if (
    $wallet < $reserved ||
    $wallet < 0 ||
    $limit < 0 ||
    $used < 0 ||
    $wallet > 900000000000000 ||
    $limit > 900000000000000
) {
                throw ValidationException::withMessages(['amount'=>'Invalid financial balance.']);
            }
            DB::table('rt_b2b_finance_accounts')->where('company_id',$companyId)->update([
                'wallet_cents'=>$wallet,'credit_limit_cents'=>$limit,'credit_used_cents'=>$used,
                'credit_frozen'=>$frozen,'updated_at'=>now()
            ]);
            DB::table('rt_b2b_finance_entries')->insert([
                'id'=>(string)Str::uuid(),'company_id'=>$companyId,'type'=>$data['type'],
                'amount_cents'=>$amount,'wallet_after_cents'=>$wallet,'limit_after_cents'=>$limit,
                'used_after_cents'=>$used,'frozen_after'=>$frozen,'reference'=>$reference,
                'reason'=>$data['reason'],'actor_id'=>$actorId,'created_at'=>now()
            ]);
        }, 3);
    }
}
