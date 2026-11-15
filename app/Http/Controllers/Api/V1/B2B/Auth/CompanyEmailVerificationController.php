<?php

namespace App\Http\Controllers\Api\V1\B2B\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Services\B2BRegistrationOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CompanyEmailVerificationController extends Controller
{
    public function verify(
        Request $request,
        B2BRegistrationOtpService $service
    ): JsonResponse {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'company_id' => ['required', 'uuid'],
            'otp' => ['required', 'digits:6'],
        ]);

        $email = strtolower(trim($data['email']));

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (
            ! $user ||
            ! $service->verify(
                $user,
                $data['company_id'],
                $data['otp']
            )
        ) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired verification code.',
            ]);
        }

        return response()->json([
            'message' => 'Email verified successfully. Your company account is now active. You can log in using your email and password.',
            'status' => 'active',
        ]);
    }

    public function resend(
        Request $request,
        B2BRegistrationOtpService $service
    ): JsonResponse {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'company_id' => ['required', 'uuid'],
        ]);

        $email = strtolower(trim($data['email']));

        $user = User::query()
            ->where('email', $email)
            ->first();

        // Return the same response regardless of account eligibility.
        $message = 'If your company is approved and your email is awaiting verification, a new code has been sent.';

        if ($user && ! $user->hasVerifiedEmail()) {
            $eligible = DB::table('rt_companies')
                ->join(
                    'rt_company_memberships',
                    'rt_company_memberships.company_id',
                    '=',
                    'rt_companies.id'
                )
                ->where('rt_companies.id', $data['company_id'])
                ->where('rt_companies.status', 'approved')
                ->where('rt_company_memberships.user_id', $user->id)
                ->where('rt_company_memberships.role_code', 'company_admin')
                ->where('rt_company_memberships.status', 'pending')
                ->exists();

            if ($eligible) {
                try {
                    $service->send(
                        $user,
                        $data['company_id']
                    );
                } catch (\Throwable $e) {
                    Log::error(
                        'B2B verification OTP resend failed.',
                        [
                            'company_id' => $data['company_id'],
                            'user_id' => $user->id,
                            'exception' => $e->getMessage(),
                        ]
                    );
                }
            }
        }

        return response()->json([
            'message' => $message,
        ]);
    }
}
