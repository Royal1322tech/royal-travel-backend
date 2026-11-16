<?php

namespace App\Modules\Identity\Services;

use App\Models\User;
use App\Notifications\B2BRegistrationOtpNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class B2BRegistrationOtpService
{
    
public function send(User $user, string $companyId): void
{
    if ($user->hasVerifiedEmail()) {
        throw ValidationException::withMessages([
            'email' => 'Email already verified.',
        ]);
    }

    $companyApproved = DB::table('rt_companies')
        ->where('id', $companyId)
        ->where('status', 'approved')
        ->exists();

    if (! $companyApproved) {
        throw ValidationException::withMessages([
            'company' => 'Company is not eligible for verification.',
        ]);
    }

    $membership = DB::table('rt_company_memberships')
        ->where('company_id', $companyId)
        ->where('user_id', $user->id)
        ->where('role_code', 'company_admin')
        ->where('status', 'pending')
        ->exists();

    if (! $membership) {
        throw ValidationException::withMessages([
            'email' => 'Company account is not eligible for verification.',
        ]);
    }

    $otp = (string) random_int(100000, 999999);
    $id = (string) Str::uuid();

    DB::table('b2b_login_challenges')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'company_id' => $companyId,
        'channel' => 'registration',
        'otp_hash' => Hash::make($otp),
        'attempts' => 0,
        'max_attempts' => 5,
        'expires_at' => now()->addMinutes(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    try {
        $user->notify(new B2BRegistrationOtpNotification($otp));
    } catch (\Throwable $e) {
        DB::table('b2b_login_challenges')
            ->where('id', $id)
            ->update([
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

        throw $e;
    }

    DB::table('b2b_login_challenges')
        ->where('user_id', $user->id)
        ->where('company_id', $companyId)
        ->where('channel', 'registration')
        ->where('id', '!=', $id)
        ->whereNull('consumed_at')
        ->update([
            'consumed_at' => now(),
            'updated_at' => now(),
        ]);
}


    public function verify(
        User $user,
        string $companyId,
        string $otp
    ): bool {
        return DB::transaction(function () use (
            $user,
            $companyId,
            $otp
        ): bool {

            $locked = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->hasVerifiedEmail()) {
                return false;
            }

            $companyApproved = DB::table('rt_companies')
                ->where('id', $companyId)
                ->where('status', 'approved')
                ->exists();

            if (! $companyApproved) {
                return false;
            }

            $membership = DB::table('rt_company_memberships')
                ->where('company_id', $companyId)
                ->where('user_id', $locked->id)
                ->where('role_code', 'company_admin')
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $membership) {
                return false;
            }

            $challenge = DB::table('b2b_login_challenges')
                ->where('user_id', $locked->id)
                ->where('company_id', $companyId)
                ->where('channel', 'registration')
                ->whereNull('consumed_at')
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if (
                ! $challenge ||
                $challenge->attempts >= $challenge->max_attempts ||
                now()->greaterThanOrEqualTo(
                    CarbonImmutable::parse($challenge->expires_at)
                )
            ) {
                return false;
            }

            DB::table('b2b_login_challenges')
                ->where('id', $challenge->id)
                ->increment('attempts');

            if (! Hash::check($otp, $challenge->otp_hash)) {
                return false;
            }

            $locked->email_verified_at = now();
            $locked->save();

            DB::table('b2b_login_challenges')
                ->where('id', $challenge->id)
                ->update([
                    'verified_at' => now(),
                    'consumed_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('rt_company_memberships')
                ->where('id', $membership->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'active',
'updated_at' => now(),
                ]);

            return true;
        });
    }
}
