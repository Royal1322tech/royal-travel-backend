<?php

namespace App\Http\Controllers\B2B\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordSetupController extends Controller
{
    private function isEligibleB2BUser(string $email): bool
    {
        return DB::table('users')
            ->join(
                'rt_company_memberships',
                'rt_company_memberships.user_id',
                '=',
                'users.id'
            )
            ->join(
                'rt_companies',
                'rt_companies.id',
                '=',
                'rt_company_memberships.company_id'
            )
            ->where('users.email', $email)
            ->where('rt_company_memberships.status', 'active')
            ->whereNotNull('rt_company_memberships.approved_at')
            ->where('rt_companies.status', 'approved')
            ->exists();
    }

    public function show(Request $request, string $token): View
    {
        return view('b2b.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(12)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $email = strtolower(trim($validated['email']));

        if (! $this->isEligibleB2BUser($email)) {
            return back()
                ->withErrors([
                    'email' => 'Invalid or expired activation link.',
                ]);
        }

        $status = Password::broker('b2b_users')->reset(
            [
                'email' => $email,
                'password' => $validated['password'],
                'password_confirmation' =>
                    $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function ($user, string $password) use ($email): void {

                DB::transaction(function () use (
                    $user,
                    $password,
                    $email
                ): void {

                    if (! $this->isEligibleB2BUser($email)) {
                        abort(403);
                    }

                    $user->forceFill([
                        'password' => Hash::make($password),
                        'must_change_password' => false,
                        'temporary_password_expires_at' => null,
                        'password_changed_at' => now(),
                        'remember_token' => Str::random(60),
                    ])->save();
                });
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Invalid or expired activation link.',
                ]);
        }

        return redirect()
            ->route('b2b.login')
            ->with(
                'success',
                'Password created successfully. Please sign in.'
            );
    }
}
