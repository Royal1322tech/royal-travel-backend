<?php
namespace App\Http\Middleware;

use App\Services\CompanyPortal\OwnerAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCompanyPortalOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $access = app(OwnerAccess::class);
        $user = $request->user();
        if (! $user) return redirect()->route('company-portal.login');
        $id = $request->session()->get('active_company_id');
        abort_unless($request->session()->get('auth_portal') === 'b2b'
            && is_string($id) && $access->eligible($user), 403);
        $company = $access->companies($user)->where('c.id', $id)->first();
        abort_unless($company, 403);
        $request->attributes->set('portal_company', $company);
        return $next($request);
    }
}
