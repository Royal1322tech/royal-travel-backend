<?php

declare(strict_types=1);

namespace App\Services\Bookings;

use App\Modules\Identity\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CustomerDirectoryService
{
    /** Company ID null denotes direct-admin B2C customers, not all customers. */
    public function search(?string $companyId, string $query = ''): LengthAwarePaginator
    {
        $q = Customer::query()->where('company_id', $companyId);
        $query = trim($query);
        if ($query !== '') {
            $escaped = addcslashes($query, '%_\\');
            $q->where(function ($builder) use ($escaped): void {
                $builder->where('first_name', 'ilike', "%{$escaped}%")
                    ->orWhere('last_name', 'ilike', "%{$escaped}%")
                    ->orWhere('email', 'ilike', "%{$escaped}%")
                    ->orWhere('phone', 'ilike', "%{$escaped}%");
            });
        }
        return $q->orderBy('last_name')->paginate(20);
    }

    public function findForBooking(string $customerId, ?string $companyId): Customer
    {
        return Customer::query()->whereKey($customerId)->where('company_id', $companyId)->firstOrFail();
    }

    public function create(?string $companyId, int $actorId, array $data): Customer
    {
        if (empty($data['email']) && empty($data['phone'])) {
            throw ValidationException::withMessages(['email' => 'Email or phone is required.']);
        }
        $customer = new Customer();
        $customer->id = (string) Str::uuid();
        $customer->company_id = $companyId;
        $customer->created_by = $actorId;
        foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
            $customer->{$field} = $data[$field] ?? null;
        }
        $customer->save();
        return $customer;
    }
}
