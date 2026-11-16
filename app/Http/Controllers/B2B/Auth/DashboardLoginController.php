<?php

namespace App\Http\Controllers\B2B\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardLoginController extends Controller
{
    public function showLogin(): View
    {
        return view('b2b.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'company_id' => ['required', 'uuid'],
        ]);
        $user = User::query()->where('email', strtolower(trim($data['email'])))->first();
        $eligible = $user && DB::table('rt_company_memberships')
            ->join('rt_companies', 'rt_companies.id', '=', 'rt_company_memberships.company_id')
            ->where('rt_company_memberships.user_id', $user->id)
            ->where('rt_company_memberships.company_id', $data['company_id'])
            ->where('rt_company_memberships.status', 'active')
            ->whereNotNull('rt_company_memberships.approved_at')
            ->where('rt_companies.status', 'approved')->exists();

        if (! $user || ! Hash::check($data['password'], $user->password)
            || $user->must_change_password || ! $user->hasVerifiedEmail() || ! $eligible) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials or inactive account.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('b2b_pending_challenge');
        $request->session()->put([
            'auth_portal' => 'b2b',
            'active_company_id' => $data['company_id'],
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
