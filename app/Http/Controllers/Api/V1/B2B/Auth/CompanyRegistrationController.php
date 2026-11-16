<?php

namespace App\Http\Controllers\Api\V1\B2B\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyRegistrationController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'country_code' => strtoupper(
                trim((string) $request->input('country_code', ''))
            ),
            'contact_email' => strtolower(
                trim((string) $request->input('contact_email', ''))
            ),
        ]);

        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'business_type' => [
                'required',
                Rule::in(['travel_agency', 'corporate']),
            ],
            'registration_no' => ['required', 'string', 'max:120'],
            'tax_no' => ['nullable', 'string', 'max:120'],
            'country_code' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Z]{2}$/',
            ],
            'city' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:2000'],
            'employee_count' => ['nullable', 'integer', 'min:0'],

            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'contact_phone' => ['required', 'string', 'max:30'],
            'contact_nationality' => ['required', 'string', 'max:100'],
            'contact_id_number' => ['required', 'string', 'max:120'],

            'password' => [
                'required',
                'confirmed',
                'min:12',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ]);

        $company = DB::transaction(function () use ($data) {
            $duplicate = DB::table('rt_companies')
                ->where('country_code', $data['country_code'])
                ->where('registration_no', $data['registration_no'])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'registration_no' =>
                        'A company with this registration already exists.',
                ]);
            }

            // Create company - pending admin approval
            $company = new Company();
            $company->id = (string) Str::uuid();
            $company->legal_name = $data['legal_name'];
            $company->display_name =
                ($data['display_name'] ?? null) ?: $data['legal_name'];
            $company->business_type = $data['business_type'];
            $company->registration_no = $data['registration_no'];
            $company->tax_no = $data['tax_no'] ?? null;
            $company->country_code = $data['country_code'];
            $company->city = $data['city'];
            $company->address = $data['address'];
            $company->employee_count = $data['employee_count'] ?? null;
            $company->preferred_locale = 'en';
            $company->account_currency = 'USD';
            $company->status = 'pending';
            $company->created_by = null;
            $company->save();

            // Create company owner
            $owner = new User();
            $owner->name = $data['contact_name'];
            $owner->email = $data['contact_email'];
            $owner->password = Hash::make($data['password']);
            $owner->must_change_password = false;
            $owner->password_changed_at = now();
            $owner->save();

            // Save primary company contact
            DB::table('rt_company_contacts')->insert([
                'id' => (string) Str::uuid(),
                'company_id' => $company->id,
                'user_id' => $owner->id,
                'full_name' => $data['contact_name'],
                'phone' => $data['contact_phone'],
                'nationality' => $data['contact_nationality'],
                'id_number_encrypted' => Crypt::encryptString(
                    $data['contact_id_number']
                ),
                'contact_type' => 'primary',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Membership remains pending until activation
            DB::table('rt_company_memberships')->insert([
                'id' => (string) Str::uuid(),
                'company_id' => $company->id,
                'user_id' => $owner->id,
                'role_code' => 'company_admin',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign B2B company admin role
            $roleId = DB::table('rt_roles')
                ->where('portal', 'b2b')
                ->where('code', 'company_admin')
                ->value('id');

            if (! $roleId) {
                throw new \RuntimeException(
                    'B2B company_admin role is missing.'
                );
            }

            DB::table('rt_user_roles')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $owner->id,
                'role_id' => $roleId,
                'company_id' => $company->id,
                'created_at' => now(),
            ]);

            return $company;
        });

        // OTP is NOT sent during registration.
        // OTP will be sent after admin approval.

        return response()->json([
            'message' => 'Registration submitted successfully. Awaiting admin approval.',
            'company_id' => $company->id,
            'status' => 'pending_admin_approval',
        ], 201);
    }
}
