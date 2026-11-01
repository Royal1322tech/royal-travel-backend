<?php

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class B2BProfileController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (
            ! $user ||
            ! $token instanceof PersonalAccessToken
        ) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $token->can('b2b:access')) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        // Extract exactly one company scope from the token.
        $companyAbilities = collect($token->abilities ?? [])
            ->filter(
                fn ($ability) =>
                    is_string($ability) &&
                    str_starts_with($ability, 'company:')
            )
            ->values();

        if ($companyAbilities->count() !== 1) {
            return response()->json([
                'message' => 'Invalid company scope.',
            ], 403);
        }

        $companyId = substr(
            $companyAbilities->first(),
            strlen('company:')
        );

        if (! Str::isUuid($companyId)) {
            return response()->json([
                'message' => 'Invalid company scope.',
            ], 403);
        }

        // Verify that the company and membership are still active.
        $membership = DB::table('rt_company_memberships')
            ->join(
                'rt_companies',
                'rt_companies.id',
                '=',
                'rt_company_memberships.company_id'
            )
            ->where(
                'rt_company_memberships.user_id',
                $user->id
            )
            ->where(
                'rt_company_memberships.company_id',
                $companyId
            )
            ->where(
                'rt_company_memberships.status',
                'active'
            )
            ->where(
                'rt_companies.status',
                'approved'
            )
            ->select([
                'rt_company_memberships.company_id',
                'rt_company_memberships.role_code',
                'rt_company_memberships.status as membership_status',

                'rt_companies.legal_name',
                'rt_companies.display_name',
                'rt_companies.status as company_status',
                'rt_companies.registration_no',
                'rt_companies.tax_no',
                'rt_companies.country_code',
                'rt_companies.preferred_locale',
                'rt_companies.account_currency',
                'rt_companies.business_type',
                'rt_companies.employee_count',
                'rt_companies.city',
                'rt_companies.address',
            ])
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => 'Company access is not available.',
            ], 403);
        }

        return response()->json([
            'message' => 'Profile retrieved successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],

                'company' => [
                    'id' => $membership->company_id,
                    'name' => $membership->display_name
                        ?: $membership->legal_name,
                    'legal_name' => $membership->legal_name,
                    'display_name' => $membership->display_name,
                    'status' => $membership->company_status,
                    'registration_no' => $membership->registration_no,
                    'tax_no' => $membership->tax_no,
                    'country_code' => $membership->country_code,
                    'preferred_locale' => $membership->preferred_locale,
                    'account_currency' => $membership->account_currency,
                    'business_type' => $membership->business_type,
                    'employee_count' => $membership->employee_count,
                    'city' => $membership->city,
                    'address' => $membership->address,
                ],

                'membership' => [
                    'role_code' => $membership->role_code,
                    'status' => $membership->membership_status,
                ],
            ],
        ]);
    }
}

