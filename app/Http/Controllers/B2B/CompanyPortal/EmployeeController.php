<?php

namespace App\Http\Controllers\B2B\CompanyPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->attributes->get('portal_company');

        $employees = DB::table('rt_company_memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.company_id', $company->id)
            ->where('m.role_code', 'company_employee')
            ->select(
                'm.id',
                'u.id as user_id',
                'u.name',
                'u.email',
                'u.email_verified_at',
                'm.status',
                'm.created_at'
            )
            ->orderByDesc('m.created_at')
            ->get();

        return view(
            'b2b.company-portal.employees.index',
            compact('company', 'employees')
        );
    }

    public function store(Request $request)
    {
        $company = $request->attributes->get('portal_company');

        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
            ],
        ]);

        try {
        DB::transaction(function () use (
            $request,
            $company,
            $data
        ) {
            $role = DB::table('rt_roles')
                ->where('code', 'company_employee')
                ->where('portal', 'b2b')
                ->first();

            abort_unless(
                $role,
                422,
                'Employee role is not configured.'
            );

            $userId = DB::table('users')->insertGetId([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('rt_company_memberships')->insert([
                'id' => (string) Str::uuid(),
                'company_id' => $company->id,
                'user_id' => $userId,
                'role_code' => 'company_employee',
                'status' => 'active',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('rt_user_roles')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'role_id' => $role->id,
                'company_id' => $company->id,
                'created_at' => now(),
            ]);
        });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request created the same email after validation passed.
            throw ValidationException::withMessages([
                'email' => 'The email has already been taken.',
            ]);
        }

        // Employees are created unverified. The existing B2B login requires a verified
        // email and there is no approved employee activation flow yet, so say so plainly.
        return redirect()
            ->route('company-portal.employees.index')
            ->with('success', 'Employee created. They cannot sign in until their email address is verified.');
    }
}