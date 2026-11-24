<?php

declare(strict_types=1);

namespace App\Http\Requests\Hotels;

use App\Services\Hotels\RateHawkDestinationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shared validation for the B2B and Admin destination autocomplete endpoints. */
final class DestinationSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentication and permissions are enforced by route middleware.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        if (is_string($this->query('q'))) {
            $normalized['q'] = trim($this->query('q'));
        }
        if (is_string($this->query('language'))) {
            $normalized['language'] = strtolower(trim($this->query('language')));
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:' . RateHawkDestinationService::MIN_QUERY,
                'max:' . RateHawkDestinationService::MAX_QUERY],
            'language' => ['sometimes', Rule::in(RateHawkDestinationService::LANGUAGES)],
            'limit' => ['sometimes', 'integer', 'between:1,' . RateHawkDestinationService::MAX_LIMIT],
        ];
    }

    /** @return array{0: string, 1: string, 2: int} query, language, limit */
    public function searchArguments(): array
    {
        $data = $this->validated();

        return [
            (string) $data['q'],
            (string) ($data['language'] ?? 'en'),
            (int) ($data['limit'] ?? RateHawkDestinationService::DEFAULT_LIMIT),
        ];
    }
}
