<?php

namespace App\Http\Requests\Admin;

use App\Modules\Identity\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user();

        return $admin !== null
            && app(PermissionService::class)->adminCan(
                $admin,
                'companies.create'
            );
    }

    public function rules(): array
    {
        return [
            // Step 1: Company Information
            'legal_name' => [
                'required',
                'string',
                'max:255',
            ],

            'display_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'business_type' => [
                'required',
                Rule::in(['travel_agency', 'corporate']),
            ],

            'employee_count' => [
                'nullable',
                'integer',
                'min:0',
                'max:1000000',
            ],

            'country_code' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Z]{2}$/',
            ],

            'city' => [
                'required',
                'string',
                'max:150',
            ],

            'address' => [
                'required',
                'string',
                'max:2000',
            ],

            'registration_no' => [
                'required',
                'string',
                'max:120',
            ],

            'tax_no' => [
                'nullable',
                'string',
                'max:120',
            ],

            'preferred_locale' => [
                'nullable',
                Rule::in(['ar', 'en']),
            ],

            'account_currency' => [
                'nullable',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],

            // Step 2: Responsible Person
            'contact_name' => [
                'required',
                'string',
                'max:255',
            ],

            'contact_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],

            'contact_phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^\+?[0-9\s().-]{7,30}$/',
            ],

            'contact_nationality' => [
                'required',
                'string',
                'max:100',
            ],

            'contact_id_number' => [
                'required',
                'string',
                'max:100',
            ],

            'contact_id_document' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],

            // Step 3: Company Documents
            'commercial_registration' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],

            'tourism_license' => [
                Rule::requiredIf(
                    fn () => $this->input('business_type') === 'travel_agency'
                ),
                Rule::prohibitedIf(
                    fn () => $this->input('business_type') === 'corporate'
                ),
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],

            'tax_certificate' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => strtoupper(
                trim((string) $this->input('country_code'))
            ),

            'contact_email' => strtolower(
                trim((string) $this->input('contact_email'))
            ),

            'registration_no' => trim(
                (string) $this->input('registration_no')
            ),

            'account_currency' => strtoupper(
                trim((string) $this->input('account_currency', 'USD'))
            ),
        ]);
    }

    public function messages(): array
    {
        return [
            'contact_email.unique' =>
                'This email is already registered.',

            'tourism_license.required' =>
                'Tourism license is required for travel agencies.',

            'tourism_license.prohibited' =>
                'Tourism license is not applicable to corporate companies.',

            'contact_id_document.max' =>
                'ID document must not exceed 5 MB.',

            'commercial_registration.max' =>
                'Commercial registration must not exceed 10 MB.',

            'tourism_license.max' =>
                'Tourism license must not exceed 10 MB.',

            'tax_certificate.max' =>
                'Tax certificate must not exceed 10 MB.',
        ];
    }
}
