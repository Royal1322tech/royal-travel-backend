<?php

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Services\Finance\B2BFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

final class B2BFinanceController extends Controller {
    public function show(Request $request,B2BFinanceService $finance) {
        $user=$request->user(); $token=$user?->currentAccessToken();
        if (!$token instanceof PersonalAccessToken || !$token->can('b2b:access')) return response()->json(['message'=>'Forbidden'],403);
        $scopes=collect($token->abilities ?? [])->filter(fn($s)=>is_string($s)&&str_starts_with($s,'company:'))->values();
        if ($scopes->count()!==1) return response()->json(['message'=>'Invalid company scope'],403);
        $companyId=substr($scopes[0],8);
        if (!Str::isUuid($companyId)) return response()->json(['message'=>'Invalid company scope'],403);
        $active=DB::table('rt_company_memberships')->join('rt_companies','rt_companies.id','=','rt_company_memberships.company_id')
            ->where('rt_company_memberships.user_id',$user->id)->where('rt_company_memberships.company_id',$companyId)
            ->where('rt_company_memberships.status','active')->whereNotNull('rt_company_memberships.approved_at')
            ->where('rt_companies.status','approved')->exists();
        if (!$active) return response()->json(['message'=>'Company access denied'],403);
        return response()->json(['account'=>$finance->snapshot($companyId),'entries'=>$finance->entries($companyId)]);
    }
}
