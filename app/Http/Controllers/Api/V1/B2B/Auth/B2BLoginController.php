<?php
namespace App\Http\Controllers\Api\V1\B2B\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Services\B2BLoginChallengeService;
use App\Notifications\B2BLoginOtpNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class B2BLoginController extends Controller
{
    /**
     * Step 1: Validate credentials and send OTP.
     */
    public function login(
        Request $request,
        B2BLoginChallengeService $service
    ): JsonResponse {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'company_id' => ['required', 'uuid'],
            'channel' => ['required', 'in:website,mobile'],
        ]);

        $email = strtolower(trim($data['email']));

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (
            ! $user ||
            ! Hash::check($data['password'], $user->password) ||
            $user->must_change_password
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'Invalid credentials or account not activated.',
                ],
            ]);
        }

        if (! $this->hasActiveCompanyAccess(
            $user->id,
            $data['company_id']
        )) {
            throw ValidationException::withMessages([
                'email' => [
                    'Company is not approved or account is inactive.',
                ],
            ]);
        }

        $challenge = $service->create(
            $user,
            $data['company_id'],
            $data['channel']
        );

        try {
            $user->notify(
                new B2BLoginOtpNotification($challenge['otp'])
            );
        } catch (Throwable $exception) {
            Log::error('B2B OTP delivery failed.', [
                'user_id' => $user->id,
                'company_id' => $data['company_id'],
                'exception' => $exception->getMessage(),
            ]);

            // Invalidate the challenge if OTP delivery fails.
            DB::table('b2b_login_challenges')
                ->where('id', $challenge['challenge_id'])
                ->update([
                    'consumed_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'message' => 'Unable to send verification code. Please try again later.',
            ], 503);
        }

        return response()->json([
            'message' => 'OTP verification required.',
            'challenge_id' => $challenge['challenge_id'],
            'expires_in' => 300,
        ]);
    }

    /**
     * Step 2: Verify OTP and issue a company-scoped Sanctum token.
     */
    public function verifyOtp(
        Request $request,
        B2BLoginChallengeService $service
    ): JsonResponse {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'otp' => ['required', 'digits:6'],
            'channel' => ['required', 'in:website,mobile'],
        ]);

        $result = $service->verify(
            $data['challenge_id'],
            $data['otp'],
            $data['channel']
        );

        if (! $result) {
            throw ValidationException::withMessages([
                'otp' => [
                    'Invalid or expired verification code.',
                ],
            ]);
        }

        $user = User::query()
            ->findOrFail($result['user_id']);

        // Recheck company and membership before issuing the token.
        if (
            $user->must_change_password ||
            ! $this->hasActiveCompanyAccess(
                $user->id,
                $result['company_id']
            )
        ) {
            return response()->json([
                'message' => 'Company access is no longer active.',
            ], 403);
        }

        /*
         * Bind the token to exactly one company.
         *
         * Example abilities:
         * [
         *     'b2b:access',
         *     'company:xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'
         * ]
         */
        $token = $user->createToken(
            'b2b-' . $data['channel'],
            [
                'b2b:access',
                'company:' . $result['company_id'],
            ],
            now()->addDays(7)
        )->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => 604800,
            'company_id' => $result['company_id'],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Step 3: Revoke the current Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Check that the company is approved
     * and the user has an active membership.
     */
    private function hasActiveCompanyAccess(
        int $userId,
        string $companyId
    ): bool {
        return DB::table('rt_company_memberships')
            ->join(
                'rt_companies',
                'rt_companies.id',
                '=',
                'rt_company_memberships.company_id'
            )
            ->where('rt_company_memberships.user_id', $userId)
            ->where('rt_company_memberships.company_id', $companyId)
            ->where('rt_company_memberships.status', 'active')
            ->where('rt_companies.status', 'approved')
            ->exists();
    }
}
