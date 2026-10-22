<?php

namespace App\Modules\Identity\Models;

use App\Core\Database\RoyalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends RoyalModel
{
    protected $table = 'rt_companies';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function company_memberships(): HasMany
    {
        return $this->hasMany(\App\Modules\Identity\Models\CompanyMembership::class, 'company_id', 'id');
    }

    public function user_roles(): HasMany
    {
        return $this->hasMany(\App\Modules\Identity\Models\UserRole::class, 'company_id', 'id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(\App\Modules\Identity\Models\File::class, 'company_id', 'id');
    }

    public function company_documents(): HasMany
    {
        return $this->hasMany(\App\Modules\Identity\Models\CompanyDocument::class, 'company_id', 'id');
    }

    public function fx_rates(): HasMany
    {
        return $this->hasMany(\App\Modules\SuppliersPricing\Models\FxRate::class, 'company_id', 'id');
    }

    public function pricing_policies(): HasMany
    {
        return $this->hasMany(\App\Modules\SuppliersPricing\Models\PricingPolicy::class, 'company_id', 'id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\Order::class, 'company_id', 'id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(\App\Modules\Bookings\Models\Booking::class, 'company_id', 'id');
    }

    public function credit_accounts(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\CreditAccount::class, 'company_id', 'id');
    }

    public function balance_accounts(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\BalanceAccount::class, 'company_id', 'id');
    }

    public function financial_movements(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\FinancialMovement::class, 'company_id', 'id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\Invoice::class, 'company_id', 'id');
    }

    public function offline_requests(): HasMany
    {
        return $this->hasMany(\App\Modules\Operations\Models\OfflineRequest::class, 'company_id', 'id');
    }

    public function group_requests(): HasMany
    {
        return $this->hasMany(\App\Modules\Operations\Models\GroupRequest::class, 'company_id', 'id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(\App\Modules\Operations\Models\Notification::class, 'company_id', 'id');
    }

    public function audit_events(): HasMany
    {
        return $this->hasMany(\App\Modules\Operations\Models\AuditEvent::class, 'company_id', 'id');
    }
}
