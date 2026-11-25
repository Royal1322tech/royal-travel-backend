<?php

namespace App\Services\CompanyPortal;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class OwnerAccess
{
    public function companies(User $user)
    {
        return DB::table('rt_company_memberships as m')
            ->join(
                'rt_companies as c',
                'c.id',
                '=',
                'm.company_id'
            )
            ->where('m.user_id', $user->id)
            ->where('m.role_code', 'company_admin')
            ->where('m.status', 'active')
            ->whereNotNull('m.approved_at')
            ->where('c.status', 'approved')
            ->whereExists(function ($q) use ($user) {
                $q->selectRaw('1')
                    ->from('rt_company_contacts as cc')
                    ->whereColumn('cc.company_id', 'c.id')
                    ->where('cc.user_id', $user->id)
                    ->where('cc.contact_type', 'primary');
            })
            ->select(
                'c.id',
                'c.legal_name',
                'c.display_name'
            );
    }

    public function eligible(User $user): bool
    {
        return !$user->must_change_password
            && $user->hasVerifiedEmail();
    }
}