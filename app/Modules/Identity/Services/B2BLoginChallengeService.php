<?php

namespace App\Modules\Identity\Services;


use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class B2BLoginChallengeService
{
    public function create(
        User $user,
        string $companyId,
        string $channel
    ): array {
        if (! in_array($channel, ['dashboard', 'website', 'mobile'], true)) {
            throw new \InvalidArgumentException('Invalid B2B channel.');
        }

        $eligible = DB::table('rt_company_memberships')
            ->join(
                'rt_companies',
                'rt_companies.id',
                '=',
                'rt_company_memberships.company_id'
            )
            ->where('rt_company_memberships.user_id', $user->id)
            ->where('rt_company_memberships.company_id', $companyId)
            ->where('rt_company_memberships.status', 'active')
            ->whereNotNull('rt_company_memberships.approved_at')
            ->where('rt_companies.status', 'approved')
            ->exists();

        if (! $eligible) {
            throw ValidationException::withMessages([
                'email' => 'Invalid login credentials.',
            ]);
        }

        $otp = (string) random_int(100000, 999999);
        $challengeId = (string) Str::uuid();

        $now = CarbonImmutable::now('UTC');

        DB::table('b2b_login_challenges')->insert([
            'id' => $challengeId,
            'user_id' => $user->id,
            'company_id' => $companyId,
            'channel' => $channel,
            'otp_hash' => Hash::make($otp),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => $now->addMinutes(5),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'challenge_id' => $challengeId,
            'otp' => $otp,
        ];
    }

    public function verify(
        string $challengeId,
        string $otp,
        string $channel
    ): ?array {
        return DB::transaction(function () use (
            $challengeId,
            $otp,
            $channel
        ) {
            $challenge = DB::table('b2b_login_challenges')
                ->where('id', $challengeId)
                ->lockForUpdate()
                ->first();

            if (! $challenge) {
                return null;
            }

            // PostgreSQL timestamptz is returned as a string
            // containing the database session's timezone offset.
            // Parse it as an absolute instant and compare in UTC.
            $expiresAt = CarbonImmutable::parse(
                $challenge->expires_at
            )->utc();

            $now = CarbonImmutable::now('UTC');

            if (
                $challenge->channel !== $channel ||
                $challenge->verified_at !== null ||
                $challenge->consumed_at !== null ||
                $now->greaterThanOrEqualTo($expiresAt) ||
                $challenge->attempts >= $challenge->max_attempts
            ) {
                return null;
            }

            // Count every attempted OTP verification.
            DB::table('b2b_login_challenges')
                ->where('id', $challengeId)
                ->increment('attempts');

            if (! Hash::check($otp, $challenge->otp_hash)) {
                return null;
            }

            // Re-check company and membership eligibility.
            $eligible = DB::table('rt_company_memberships')
                ->join(
                    'rt_companies',
                    'rt_companies.id',
                    '=',
                    'rt_company_memberships.company_id'
                )
                ->where(
                    'rt_company_memberships.user_id',
                    $challenge->user_id
                )
                ->where(
                    'rt_company_memberships.company_id',
                    $challenge->company_id
                )
                ->where(
                    'rt_company_memberships.status',
                    'active'
                )
                ->whereNotNull('rt_company_memberships.approved_at')
                ->where(
                    'rt_companies.status',
                    'approved'
                )
                ->exists();

            if (! $eligible) {
                return null;
            }

            // Consume the challenge to prevent OTP reuse.
            DB::table('b2b_login_challenges')
                ->where('id', $challengeId)
                ->update([
                    'verified_at' => $now,
                    'consumed_at' => $now,
                    'updated_at' => $now,
                ]);

            return [
                'user_id' => $challenge->user_id,
                'company_id' => $challenge->company_id,
            ];
        });
    }
}
