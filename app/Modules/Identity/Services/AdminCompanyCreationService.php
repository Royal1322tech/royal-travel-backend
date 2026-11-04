<?php

namespace App\Modules\Identity\Services;

use App\Models\User;
use App\Modules\Identity\Models\Company;
use App\Notifications\B2BAccountInvitation;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AdminCompanyCreationService
{
    public function createCompany(User $admin, array $data): Company
    {
        if (! app(PermissionService::class)->adminCan(
            $admin,
            'companies.create'
        )) {
            abort(403);
        }

        $disk = 'local';
        $storedPaths = [];
        $email = strtolower(trim($data['contact_email']));

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'contact_email' => 'This email is already registered.',
            ]);
        }

        try {
            [$company, $owner] = DB::transaction(
                function () use (
                    $admin,
                    $data,
                    $email,
                    $disk,
                    &$storedPaths
                ) {
                    $duplicate = DB::table('rt_companies')
                        ->where('country_code', $data['country_code'])
                        ->where('registration_no', $data['registration_no'])
                        ->exists();

                    if ($duplicate) {
                        throw ValidationException::withMessages([
                            'registration_no' =>
                                'This company registration already exists.',
                        ]);
                    }

                    $company = new Company();
$company->id = (string) Str::uuid();

                    $company->legal_name = $data['legal_name'];
                   $company->display_name =
    ($data['display_name'] ?? null) ?: $data['legal_name'];
                    $company->status = 'approved';
                    $company->registration_no = $data['registration_no'];
                    $company->tax_no = $data['tax_no'] ?? null;
                    $company->country_code = $data['country_code'];
                    $company->business_type = $data['business_type'];
                    $company->employee_count =
                        $data['employee_count'] ?? null;
                    $company->city = $data['city'];
                    $company->address = $data['address'];
                    $company->preferred_locale =
                        $data['preferred_locale'] ?? 'en';
                    $company->account_currency =
                        $data['account_currency'] ?? 'USD';
                    $company->created_by = $admin->id;

                    $company->save();

                    // No shared/default password.
                    // Owner sets their own password using invitation.
                    $owner = new User();
                    $owner->name = $data['contact_name'];
                    $owner->email = $email;
                    $owner->password = Hash::make(Str::random(64));
                    $owner->must_change_password = true;
                    $owner->temporary_password_expires_at = null;
                    $owner->save();

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

                    DB::table('rt_company_memberships')->insert([
                        'id' => (string) Str::uuid(),
                        'company_id' => $company->id,
                        'user_id' => $owner->id,
                        'role_code' => 'company_admin',
                        'status' => 'active',
                        'approved_by' => $admin->id,
                        'approved_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $role = DB::table('rt_roles')
                        ->where('code', 'company_admin')
                        ->where('portal', 'b2b')
                        ->first();

                    if (! $role) {
                        throw new RuntimeException(
                            'B2B company_admin role is not configured.'
                        );
                    }

                    DB::table('rt_user_roles')->insert([
                        'id' => (string) Str::uuid(),
                        'user_id' => $owner->id,
                        'role_id' => $role->id,
                        'company_id' => $company->id,
                        'created_at' => now(),
                    ]);

                    $documents = [
                        'contact_id_document' => 'contact_id',
                        'commercial_registration' =>
                            'commercial_registration',
                        'tourism_license' => 'tourism_license',
                        'tax_certificate' => 'tax_certificate',
                    ];

                    foreach ($documents as $input => $type) {
                        if (empty($data[$input])) {
                            continue;
                        }

                        $file = $data[$input];

                        $path = $file->store(
                            'companies/' . $company->id . '/documents',
                            $disk
                        );

                        if ($path === false) {
                            throw new RuntimeException(
                                'Failed to store company document.'
                            );
                        }

                        $storedPaths[] = $path;

                        $fileId = (string) Str::uuid();

                        DB::table('rt_files')->insert([
                            'id' => $fileId,
                            'company_id' => $company->id,
                            'uploaded_by' => $admin->id,
                            'disk' => $disk,
                            'path' => $path,
                            'original_name' =>
                                $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType(),
                            'byte_size' => $file->getSize(),
                            'visibility' => 'private',
                            'checksum_sha256' => hash_file(
                                'sha256',
                                $file->getRealPath()
                            ),
                            'created_at' => now(),
                        ]);

                        DB::table('rt_company_documents')->insert([
                            'id' => (string) Str::uuid(),
                            'company_id' => $company->id,
                            'file_id' => $fileId,
                            'document_type' => $type,
                            'review_status' => 'pending',
                            'created_at' => now(),
                        ]);
                    }

                    return [$company, $owner];
                }
            );
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }

        // Invitation happens AFTER company creation.
        // Mail failure must not roll back the company.
        try {
            $broker = Password::broker('b2b_users');
            $token = $broker->createToken($owner);

            $owner->notify(
                new B2BAccountInvitation(
                    $token,
                    $company->display_name
                )
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        return $company;
    }
}
