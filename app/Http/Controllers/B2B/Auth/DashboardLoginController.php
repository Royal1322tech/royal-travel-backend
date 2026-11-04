<?php

namespace App\Http\Controllers\B2B\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Services\B2BLoginChallengeService;
use App\Notifications\B2BLoginOtpNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class DashboardLoginController extends Controller
{
    public function showLogin(): View
    {
        return view('b2b.auth.login');
    }

    public function login(
        Request $request,
        B2BLoginChallengeService $service
    ): RedirectResponse {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'company_id' => ['required', 'uuid'],
        ]);

        $user = User::query()
            ->where('email', strtolower(trim($data['email'])))
            ->first();

        if (
            ! $user ||
            ! Hash::check($data['password'], $user->password) ||
            $user->must_change_password
        ) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials or inactive account.',
            ]);
        }

        $challenge = $service->create(
            $user,
            $data['company_id'],
            'dashboard'
        );

        try {
            $user->notify(
                new B2BLoginOtpNotification($challenge['otp'])
            );
        } catch (Throwable $exception) {
            Log::error('B2B dashboard OTP delivery failed.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);

            DB::table('b2b_login_challenges')
                ->where('id', $challenge['challenge_id'])
                ->update([
                    'consumed_at' => now(),
                    'updated_at' => now(),
                ]);

            return back()->withErrors([
                'email' => 'Unable to send verification code.',
            ]);
        }

        $request->session()->put(
            'b2b_pending_challenge',
            $challenge['challenge_id']
        );

        return redirect()->route('b2b.otp.show');
    }

    public function showOtp(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('b2b_pending_challenge')) {
            return redirect()->route('b2b.login');
        }

        return view('b2b.auth.verify-otp');
    }

    public function verifyOtp(
        Request $request,
        B2BLoginChallengeService $service
    ): RedirectResponse {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $challengeId = $request->session()
            ->get('b2b_pending_challenge');

        if (! is_string($challengeId)) {
            return redirect()->route('b2b.login');
        }

        $result = $service->verify(
            $challengeId,
            $data['otp'],
            'dashboard'
        );

        if (! $result) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired verification code.',
            ]);
        }

        $user = User::query()->findOrFail($result['user_id']);

        if ($user->must_change_password) {
            abort(403, 'Account activation required.');
        }

        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->forget('b2b_pending_challenge');

        $request->session()->put([
            'auth_portal' => 'b2b',
            'active_company_id' => $result['company_id'],
        ]);

        return redirect()->route('b2b.hotels.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('b2b.login');
    }
}
