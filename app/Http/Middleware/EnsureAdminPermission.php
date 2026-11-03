<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPermission
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

        if ($request->session()->get('auth_portal') !== 'admin') {
            abort(403, 'Admin dashboard authentication required.');
        }

        if (! $this->permissions->adminCan($user, $permission)) {
            abort(403, 'You do not have admin permission.');
        }

        return $next($request);
    }
}