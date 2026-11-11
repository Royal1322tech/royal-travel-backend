<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureB2BApiPermission
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
        $token = $user?->currentAccessToken();

        if (!$user || !$token instanceof PersonalAccessToken) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$token->can('b2b:access')) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $companyScopes = collect($token->abilities ?? [])
            ->filter(
                fn ($ability) =>
                    is_string($ability) &&
                    str_starts_with($ability, 'company:')
            )
            ->values();

        if ($companyScopes->count() !== 1) {
            return response()->json([
                'message' => 'Invalid company scope.',
            ], 403);
        }

        $companyId = substr(
            $companyScopes->first(),
            strlen('company:')
        );

        if (!Str::isUuid($companyId)) {
            return response()->json([
                'message' => 'Invalid company scope.',
            ], 403);
        }

        if (!$this->permissions->userCan(
            $user,
            $permission,
            $companyId
        )) {
            return response()->json([
                'message' => 'You do not have permission.',
            ], 403);
        }

        // Trusted company context for downstream controllers.
        $request->attributes->set(
            'authenticated_company_id',
            strtolower($companyId)
        );

        return $next($request);
    }
}
