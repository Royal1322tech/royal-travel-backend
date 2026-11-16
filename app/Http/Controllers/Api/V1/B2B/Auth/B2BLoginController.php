<?php
namespace App\Http\Controllers\Api\V1\B2B\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class B2BLoginController extends Controller
{
    /** Authenticate directly after email verification and admin approval. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'company_id' => ['required', 'uuid'],
            'channel' => ['required', 'in:website,mobile'],
        ]);

        $user = User::query()->where('email', strtolower(trim($data['email'])))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)
            || $user->must_change_password || ! $user->hasVerifiedEmail()
            || ! $this->hasActiveCompanyAccess($user->id, $data['company_id'])) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials or account not activated.'],
            ]);
        }

        $token = $user->createToken(
            'b2b-' . $data['channel'],
            ['b2b:access', 'company:' . $data['company_id']],
            now()->addDays(7)
        )->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => 604800,
            'company_id' => $data['company_id'],
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    /**
     * Step 3: Revoke the current Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Check that the company is approved
     * and the user has an active membership.
     */
    private function hasActiveCompanyAccess(
        int $userId,
        string $companyId
    ): bool {
        return DB::table('rt_company_memberships')
            ->join(
                'rt_companies',
                'rt_companies.id',
                '=',
                'rt_company_memberships.company_id'
            )
            ->where('rt_company_memberships.user_id', $userId)
            ->where('rt_company_memberships.company_id', $companyId)
            ->where('rt_company_memberships.status', 'active')
            ->whereNotNull('rt_company_memberships.approved_at')
            ->where('rt_companies.status', 'approved')
            ->exists();
    }
}
