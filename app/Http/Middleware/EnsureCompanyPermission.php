<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyPermission
{
    public function __construct(
        private readonly PermissionService $permissions
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        string $permission
    ): Response {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Company context must come from a trusted, validated session.
        $companyId = $request->session()->get('active_company_id');

        if (! is_string($companyId) || $companyId === '') {
            abort(403, 'No active company selected.');
        }

        if (! $this->permissions->userCan(
            $user,
            $permission,
            $companyId
        )) {
            abort(403, 'You do not have permission.');
        }

        return $next($request);
    }
}