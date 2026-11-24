<?php

declare(strict_types=1);

namespace App\Services\Hotels;

use App\Services\Suppliers\RateHawk\RateHawkClient;
use InvalidArgumentException;
use RuntimeException;

/**
 * Low-level RateHawk booking API adapter. No persistence or wallet mutations.
 * Caller MUST persist the attempt/partner_order_id before any supplier call,
 * serialize concurrent requests, and reconcile ambiguous outcomes.
 */
final class RateHawkBookingService
{
    public function __construct(private readonly RateHawkClient $client) {}

    public function createForm(string $partnerOrderId, string $prebookHash, string $userIp): array
    {
        self::assertUuid($partnerOrderId);
        if (!preg_match('/^p-\S{1,254}$/D', $prebookHash)) {
            throw new InvalidArgumentException('Invalid prebook hash.');
        }
        if (filter_var($userIp, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Invalid user IP.');
        }

        $response = $this->client->post('/api/b2b/v3/hotel/order/booking/form/', [
            'partner_order_id' => $partnerOrderId,
            'book_hash' => $prebookHash,
            'language' => 'en',
            'user_ip' => $userIp,
        ], 60);

        self::requireOk($response, 'Create booking form');
        $data = $response['data'] ?? null;
        if (!is_array($data)
            || !is_int($data['order_id'] ?? null)
            || $data['order_id'] <= 0
            || ($data['partner_order_id'] ?? null) !== $partnerOrderId
            || !is_array($data['payment_types'] ?? null)
            || $data['payment_types'] === []) {
            throw new RuntimeException('Invalid booking form response.');
        }
        return [
            'order_id' => $data['order_id'],
            'partner_order_id' => $partnerOrderId,
            'payment_types' => $data['payment_types'],
            'is_gender_specification_required' => $data['is_gender_specification_required'] ?? false,
            'upsell_data' => $data['upsell_data'] ?? [],
        ];
    }

    /**
     * Start a deposit-paid booking using the exact payment option returned by createForm().
     * The partner's ETG deposit is separate from Royal Travel's B2B wallet.
     * $rooms must match the original hotelpage occupancy exactly.
     */
    public function startBooking(
        string $partnerOrderId,
        array $bookingForm,
        array $rooms,
        string $contactEmail,
        string $contactPhone,
        ?string $hotelComment = null
    ): array {
        $payload = $this->buildFinishPayload(
            $partnerOrderId, $bookingForm, $rooms, $contactEmail, $contactPhone, $hotelComment
        );

        // IMPORTANT: On HTTP timeout/5xx or API error 'timeout'/'unknown', do not
        // resend finish blindly. Persist UNKNOWN and checkStatus() instead.
        $response = $this->client->post('/api/b2b/v3/hotel/order/booking/finish/', $payload, 60);
        self::requireOk($response, 'Start booking');
        return ['status' => 'submitted', 'partner_order_id' => $partnerOrderId];
    }

    /**
     * The exact deposit option that startBooking() submits to the supplier:
     * the FIRST deposit option without card data whose amount is a supported
     * decimal string and whose currency is an ISO code. Callers that validate
     * the deposit amount MUST use this method so they check the same option.
     */
    public static function selectDeposit(mixed $paymentOptions): ?array
    {
        if (!is_array($paymentOptions)) {
            return null;
        }
        foreach ($paymentOptions as $option) {
            if (!is_array($option) || ($option['type'] ?? null) !== 'deposit') {
                continue;
            }
            if (($option['is_need_credit_card_data'] ?? false) !== false) {
                continue;
            }
            if (self::validAmount($option['amount'] ?? null)
                && is_string($option['currency_code'] ?? null)
                && preg_match('/^[A-Z]{3}$/D', $option['currency_code'])) {
                return [
                    'type' => $option['type'],
                    'amount' => $option['amount'],
                    'currency_code' => $option['currency_code'],
                ];
            }
        }
        return null;
    }

    /**
     * Validate local Finish input and build the exact supplier payload WITHOUT
     * sending it. Callers run this before committing an irreversible-send marker,
     * so invalid local input can never leave an attempt in an unknown state.
     */
    public function buildFinishPayload(
        string $partnerOrderId,
        array $bookingForm,
        array $rooms,
        string $contactEmail,
        string $contactPhone,
        ?string $hotelComment = null
    ): array {
        self::assertUuid($partnerOrderId);
        if (($bookingForm['partner_order_id'] ?? null) !== $partnerOrderId) {
            throw new InvalidArgumentException('Booking form does not match partner order ID.');
        }
        $paymentOptions = $bookingForm['payment_types'] ?? null;
        if (!is_array($paymentOptions)) {
            throw new InvalidArgumentException('Booking form payment options missing.');
        }
        $deposit = self::selectDeposit($paymentOptions);
        if ($deposit === null) {
            throw new RuntimeException('No supported deposit payment option on booking form.');
        }
        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid contact email.');
        }
        if (strlen($contactPhone) < 5 || strlen($contactPhone) > 35) {
            throw new InvalidArgumentException('Invalid contact phone.');
        }
        if ($hotelComment !== null && (mb_strlen($hotelComment) < 1 || mb_strlen($hotelComment) > 256)) {
            throw new InvalidArgumentException('Invalid hotel comment length.');
        }
        if ($rooms === [] || !array_is_list($rooms)) {
            throw new InvalidArgumentException('Rooms must be a nonempty list.');
        }
        $requiresGender = ($bookingForm['is_gender_specification_required'] ?? false) === true;
        foreach ($rooms as $room) {
            if (!is_array($room) || !array_is_list($room['guests'] ?? null) || $room['guests'] === []) {
                throw new InvalidArgumentException('Each room must have guests.');
            }
            foreach ($room['guests'] as $guest) {
                if (!is_array($guest)) {
                    throw new InvalidArgumentException('Invalid guest.');
                }
                foreach (['first_name', 'last_name'] as $key) {
                    $name = $guest[$key] ?? null;
                    if (!is_string($name) || !self::validGuestName($name)) {
                        throw new InvalidArgumentException("Invalid guest {$key}.");
                    }
                }
                if (array_key_exists('is_child', $guest) && !is_bool($guest['is_child'])) {
                    throw new InvalidArgumentException('Invalid child flag.');
                }
                if (($guest['is_child'] ?? false) === true
                    && (!is_int($guest['age'] ?? null) || $guest['age'] < 0 || $guest['age'] > 17)) {
                    throw new InvalidArgumentException('Child age must be between 0 and 17.');
                }
                if ($requiresGender && !isset($guest['gender'])) {
                    throw new InvalidArgumentException('Guest gender is required by supplier.');
                }
                if (isset($guest['gender']) && !in_array($guest['gender'], ['male', 'female', 'unknown'], true)) {
                    throw new InvalidArgumentException('Invalid guest gender.');
                }
                foreach (array_keys($guest) as $key) {
                    if (!in_array($key, ['first_name', 'last_name', 'is_child', 'age', 'gender'], true)) {
                        throw new InvalidArgumentException('Unexpected guest field.');
                    }
                }
            }
        }
        $payload = [
            'language' => 'en',
            'partner' => ['partner_order_id' => $partnerOrderId],
            'payment_type' => $deposit,
            'rooms' => $rooms,
            'user' => ['email' => $contactEmail, 'phone' => $contactPhone],
        ];
        if ($hotelComment !== null) {
            $payload['user']['comment'] = $hotelComment;
        }
        return $payload;
    }

    /** Guest name format accepted by the supplier Finish request. */
    public static function validGuestName(string $name): bool
    {
        return mb_strlen($name) >= 1 && mb_strlen($name) <= 50
            && preg_match('/^[\p{L}\p{M} \'\-,\.\x{2019}]+$/uD', $name) === 1;
    }

    /** One status check only. Polling and deadlines belong to the orchestrator. */
    public function checkStatus(string $partnerOrderId): array
    {
        self::assertUuid($partnerOrderId);
        $response = $this->client->post('/api/b2b/v3/hotel/order/booking/finish/status/', [
            'partner_order_id' => $partnerOrderId,
        ], 30);
        $status = $response['status'] ?? null;
        if (!in_array($status, ['ok', 'processing', 'error', '3ds'], true)) {
            throw new RuntimeException('Unknown or ambiguous booking status response.');
        }
        $data = $response['data'] ?? null;
        if (is_array($data) && isset($data['partner_order_id']) && $data['partner_order_id'] !== $partnerOrderId) {
            throw new RuntimeException('Booking status partner order ID mismatch.');
        }
        return [
            'status' => $status,
            'error' => $response['error'] ?? null,
            'data' => $data,
        ];
    }

    private static function assertUuid(string $id): void
    {
        if (!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD', $id)) {
            throw new InvalidArgumentException('Invalid partner order ID UUID.');
        }
    }

    private static function validAmount(mixed $amount): bool
    {
        return is_string($amount) && preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D', $amount) === 1
            && (float) $amount > 0;
    }

    private static function requireOk(array $response, string $operation): void
    {
        if (($response['status'] ?? null) !== 'ok' || ($response['error'] ?? null) !== null) {
            $error = $response['error'] ?? 'unknown';
            throw new RuntimeException("{$operation} failed: " . (is_string($error) ? $error : 'unknown'));
        }
    }
}
