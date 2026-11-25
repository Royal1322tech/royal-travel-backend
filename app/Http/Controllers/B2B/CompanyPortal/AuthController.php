<?php
namespace App\Http\Controllers\B2B\CompanyPortal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CompanyPortal\OwnerAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function show(Request $request)
    {
        return view('b2b.company-portal.login');
    }

    public function login(Request $request, OwnerAccess $access)
    {
        $data = $request->validate(['email'=>['required','email','max:255'], 'password'=>['required','string','max:1024'], 'company_id'=>['nullable','uuid']]);
        $user = User::query()->where('email', strtolower(trim($data['email'])))->first();
        $fail = fn () => ValidationException::withMessages(['email'=>'Invalid credentials or inactive owner account.']);
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $access->eligible($user)) throw $fail();
        $query = $access->companies($user);
        if (! empty($data['company_id'])) $query->where('c.id', $data['company_id']);
        $companies = $query->limit(2)->get();
        if ($companies->isEmpty()) throw $fail();
        if ($companies->count() !== 1) throw ValidationException::withMessages(['company_id'=>'Multiple companies found. Enter your company ID.']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($user);
        $request->session()->put(['auth_portal'=>'b2b','active_company_id'=>(string)$companies->first()->id]);
        return redirect()->route('company-portal.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('company-portal.login');
    }
}
