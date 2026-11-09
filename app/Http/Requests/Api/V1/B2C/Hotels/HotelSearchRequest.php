<?php

namespace App\Http\Requests\Api\V1\B2C\Hotels;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HotelSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public B2C hotel search.
        // Supplier entitlements are enforced in the service layer.
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Do not silently replace user-supplied values.
        // Normalize only the supported text codes.
        $normalized = [];

        foreach (['residency', 'language', 'currency'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = $field === 'currency'
                    ? strtoupper(trim($value))
                    : strtolower(trim($value));
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'checkin' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],

            'checkout' => [
                'required',
                'date_format:Y-m-d',
                'after:checkin',
            ],

            // Geographic search (initial implementation).
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'radius' => [
                'sometimes',
                'integer',
                'between:1,10000',
            ],

            'residency' => [
                'required',
                'string',
                'size:2',
                'regex:/^[a-z]{2}$/',
            ],

            'language' => [
                'sometimes',
                Rule::in(['en', 'ar']),
            ],

            // Display currency only. Supplier requests remain in USD; no public prices until verified.
            'currency' => [
                'sometimes',
                Rule::in(['USD', 'EGP', 'SAR']),
            ],

            // Each entry represents one room.
            'guests' => [
                'required',
                'array',
                'min:1',
                'max:9',
            ],

            'guests.*' => [
                'required',
                'array:adults,children',
            ],

            'guests.*.adults' => [
                'required',
                'integer',
                'between:1,6',
            ],

            'guests.*.children' => [
                'present',
                'array',
                'max:4',
            ],

            'guests.*.children.*' => [
                'required',
                'integer',
                'between:0,17',
            ],

            'filters' => [
                'sometimes',
                'array:hotel_name,area,stars,board,amenities,package_type',
            ],

            'filters.hotel_name' => [
                'sometimes',
                'string',
                'max:120',
            ],

            'filters.area' => [
                'sometimes',
                'string',
                'max:120',
            ],

            'filters.stars' => [
                'sometimes',
                'array',
                'max:5',
            ],

            'filters.stars.*' => [
                'integer',
                Rule::in([1, 2, 3, 4, 5]),
            ],

            'filters.board' => [
                'sometimes',
                'array',
                'max:6',
            ],

            'filters.board.*' => [
                'string',
                Rule::in([
                    'nomeal',
                    'breakfast',
                    'half-board',
                    'full-board',
                    'all-inclusive',
                    'ultra-all-inclusive',
                ]),
            ],

            'filters.amenities' => [
                'sometimes',
                'array',
                'max:6',
            ],

            'filters.amenities.*' => [
                'string',
                Rule::in([
                    'non_smoking',
                    'room_service',
                    'air_conditioning',
                    'bathroom',
                    'gym',
                    'heating',
                ]),
            ],

            'filters.package_type' => [
                'sometimes',
                Rule::in([
                    'all',
                    'with_package',
                    'without_package',
                ]),
            ],

            'sort' => [
                'sometimes',
                Rule::in([
                    'recommended',
                    'stars_desc',
                    'name_asc',
                ]),
            ],

            'page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:1000',
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'between:1,20',
            ],
        ];
    }
}
