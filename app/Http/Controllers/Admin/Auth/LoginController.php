<?php
namespace App\Http\Controllers\Admin\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
class LoginController extends Controller {
 public function showLoginForm(): View { return view('admin.auth.login'); }
 public function login(Request $request): RedirectResponse {
  $credentials=$request->validate(['email'=>['required','email'],'password'=>['required','string']]);
  if (!Auth::attempt($credentials)) throw ValidationException::withMessages(['email'=>'Invalid email or password.']);
  $user=Auth::user();
  $allowed=DB::table('rt_user_roles as ur')->join('rt_roles as r','r.id','=','ur.role_id')->join('rt_role_permissions as rp','rp.role_id','=','r.id')->join('rt_permissions as p','p.id','=','rp.permission_id')->where('ur.user_id',$user->id)->whereNull('ur.company_id')->where('r.portal','admin')->where('p.code','dashboard.view')->exists();
  $enabled=DB::table('rt_admin_access')->where('user_id',$user->id)->where('is_enabled',true)->exists();
  if (!$allowed || !$enabled) {
   Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
   throw ValidationException::withMessages(['email'=>'You are not authorized to access Admin.']);
  }
  if ($user->must_change_password || ($user->temporary_password_expires_at && now()->greaterThan($user->temporary_password_expires_at))) {
   Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
   throw ValidationException::withMessages(['email'=>'Temporary password change is required. Contact your administrator.']);
  }
  $request->session()->regenerate(); $request->session()->forget(['active_company_id','b2b_pending_challenge']); $request->session()->put('auth_portal','admin');
  return redirect()->route('admin.dashboard');
 }
 public function logout(Request $request): RedirectResponse { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('admin.login'); }
}
