<?php

namespace App\Http\Controllers\Api\V1\B2B\Bookings;

use App\Http\Controllers\Controller;
use App\Services\Bookings\B2BBookingDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class BookingDraftController extends Controller
{
    public function store(Request $request, B2BBookingDraftService $service): JsonResponse
    {
        abort_unless($request->user() !== null, 401, 'Unauthenticated.');
        $companyId = $request->attributes->get('authenticated_company_id');
        abort_unless(is_string($companyId) && Str::isUuid($companyId), 403, 'Invalid company context.');

        $idempotencyKey = $request->header('Idempotency-Key');
        abort_unless(is_string($idempotencyKey) && preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $idempotencyKey),
            422, 'A valid Idempotency-Key header (16-128 characters) is required.');

        $data = $request->validate([
            'review_token' => ['required', 'string', 'regex:/^[a-f0-9]{48}$/D'],
            'customer_id' => ['required_without:customer', 'nullable', 'uuid', 'prohibits:customer'],
            'customer' => ['required_without:customer_id', 'nullable', 'array', 'prohibits:customer_id'],
            'customer.first_name' => ['required_with:customer', 'string', 'max:100'],
            'customer.last_name' => ['required_with:customer', 'string', 'max:100'],
            'customer.email' => ['nullable', 'email', 'max:254'],
            'customer.phone' => ['nullable', 'string', 'max:35'],
            'guests' => ['required', 'array', 'min:1', 'max:64'],
            'guests.*.first_name' => ['required', 'string', 'max:100'],
            'guests.*.last_name' => ['required', 'string', 'max:100'],
            'guests.*.guest_type' => ['required', 'in:adult,child'],
            'guests.*.date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'guests.*.nationality_code' => ['nullable', 'string', 'regex:/^[A-Za-z]{2}$/D'],
            'contact_email' => ['required', 'email', 'max:254'],
            'contact_phone' => ['required', 'string', 'min:5', 'max:35'],
            'guests.*.room_index' => ['required', 'integer', 'min:0', 'max:7'],
            'guests.*.age' => ['required_if:guests.*.guest_type,child', 'nullable', 'integer', 'min:0', 'max:17'],
            'guests.*.gender' => ['nullable', 'in:male,female,unknown'],
            'guests.*.is_lead' => ['required', 'boolean'],
        ]);

        $result = $service->create(
            (int) $request->user()->getAuthIdentifier(),
            strtolower($companyId),
            $data,
            $idempotencyKey
        );

        return response()->json($result, 201)->header('Cache-Control', 'no-store');
    }
}
