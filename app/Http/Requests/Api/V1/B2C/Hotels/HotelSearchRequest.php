<?php

namespace App\Http\Requests\Api\V1\B2C\Hotels;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\Hotels\RateHawkDestinationService;
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
        // Normalize only supported text codes.
        // Preserve all other user-supplied values.
        $normalized = [];

        foreach (['residency', 'language', 'currency', 'destination'] as $field) {
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

            // Either a destination token from the destinations API
            // ("region:<id>" or a legacy slug such as "dubai"), or coordinates.
            'destination' => [
                'required_without_all:latitude,longitude',
                'string',
                'max:40',
                'regex:' . RateHawkDestinationService::TOKEN_PATTERN,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value)
                        && app(RateHawkDestinationService::class)->resolve($value) === null) {
                        $fail('The selected destination is not supported.');
                    }
                },
            ],

            'latitude' => [
                'required_without:destination',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required_without:destination',
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

            // Display currency only.
            // Supplier requests remain in USD.
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

            /*
            |--------------------------------------------------------------------------
            | Hotel Search Filters
            |--------------------------------------------------------------------------
            | These filters are shared by B2B and B2C search.
            | They must be applied by HotelSearchApplicationService
            | before pagination.
            */

            'filters' => [
                'sometimes',
                'array:hotel_name,area,stars,board,amenities,package_type',
            ],

            // Hotel Name
            'filters.hotel_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],

            // Area / Location
            'filters.area' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],

            // Star Category: 1 to 5 stars
            'filters.stars' => [
                'sometimes',
                'array',
                'max:5',
            ],

            'filters.stars.*' => [
                'required',
                'integer',
                'distinct',
                Rule::in([1, 2, 3, 4, 5]),
            ],

            // Board / Meal Plan
            'filters.board' => [
                'sometimes',
                'array',
                'max:6',
            ],

            'filters.board.*' => [
                'required',
                'string',
                'distinct',
                Rule::in([
                    'nomeal',
                    'breakfast',
                    'half-board',
                    'full-board',
                    'all-inclusive',
                    'ultra-all-inclusive',
                ]),
            ],

            // Hotel-level amenities
            'filters.amenities' => [
                'sometimes',
                'array',
                'max:6',
            ],

            'filters.amenities.*' => [
                'required',
                'string',
                'distinct',
                Rule::in([
                    'non_smoking',
                    'room_service',
                    'air_conditioning',
                    'bathroom',
                    'gym',
                    'heating',
                ]),
            ],

            // Product Packaging
            'filters.package_type' => [
                'sometimes',
                Rule::in([
                    'all',
                    'with_package',
                    'without_package',
                ]),
            ],

            // Sorting
            'sort' => [
                'sometimes',
                Rule::in([
                    'recommended',
                    'stars_desc',
                    'name_asc',
                ]),
            ],

            // Pagination
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
