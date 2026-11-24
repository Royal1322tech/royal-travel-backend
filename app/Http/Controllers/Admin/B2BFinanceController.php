<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\Company;
use App\Services\Finance\B2BFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
final class B2BFinanceController extends Controller {
    public function index(Request $request) {
        $filters = $request->validate(['search'=>['nullable','string','max:150'],'credit_status'=>['nullable',Rule::in(['active','frozen'])]]);
        $search=trim($filters['search'] ?? ''); $creditStatus=$filters['credit_status'] ?? '';
        $query=DB::table('rt_companies as c')->leftJoin('rt_b2b_finance_accounts as a','a.company_id','=','c.id')
            ->when($search !== '',fn($q)=>$q->where(fn($q)=>$q->where('c.legal_name','ilike','%'.$search.'%')->orWhere('c.display_name','ilike','%'.$search.'%')->orWhere('c.registration_no','ilike','%'.$search.'%')))
            ->when($creditStatus !== '',fn($q)=>$q->whereRaw('COALESCE(a.credit_frozen,false) = ?',[$creditStatus === 'frozen' ? 'true' : 'false']));
        $stats=(clone $query)->selectRaw('COALESCE(SUM(a.wallet_cents),0) AS wallet, COALESCE(SUM(a.wallet_reserved_cents),0) AS reserved, COALESCE(SUM(a.credit_limit_cents),0) AS credit_limit, COALESCE(SUM(a.credit_used_cents),0) AS credit_used, COALESCE(SUM(GREATEST(0,COALESCE(a.wallet_cents,0)-COALESCE(a.wallet_reserved_cents,0))),0) AS wallet_available, COALESCE(SUM(CASE WHEN a.credit_frozen THEN 0 ELSE GREATEST(0,COALESCE(a.credit_limit_cents,0)-COALESCE(a.credit_used_cents,0)) END),0) AS credit_available')->first();
        $companies=$query->select('c.id','c.legal_name','c.display_name','c.status','a.wallet_cents','a.wallet_reserved_cents','a.credit_limit_cents','a.credit_used_cents','a.credit_frozen')->orderByDesc('c.created_at')->orderBy('c.id')->paginate(15)->withQueryString();
        return view('admin.company-finance.index',compact('companies','stats','search','creditStatus'));
    }
    public function show(Company $company,B2BFinanceService $finance) {
        $financeAccount=$finance->snapshot($company->id); $financeEntries=$finance->entries($company->id);
        return view('admin.company-finance.show',compact('company','financeAccount','financeEntries'));
    }
    public function update(Request $request,Company $company,B2BFinanceService $finance) {
        $data=$request->validate([
            'type'=>['required',Rule::in(['wallet_topup','set_credit_limit','credit_settlement','credit_freeze','credit_unfreeze'])],
            'amount'=>['required_unless:type,credit_freeze,credit_unfreeze','nullable','regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'reference'=>['required','string','max:100'],
            'reason'=>['required','string','min:5','max:2000']
        ]);
        $type=$data['type'];
        if (isset($data['amount'])) {
            [$whole,$fraction]=array_pad(explode('.',$data['amount'],2),2,'');
            $data['amount_cents']=((int)$whole)*100+(int)str_pad($fraction,2,'0');
            if ($data['amount_cents'] === 0 && $type !== 'set_credit_limit') {
                throw ValidationException::withMessages(['amount'=>'Amount must be greater than zero.']);
            }
        }
        $finance->apply($company->id,(string)$request->user()->id,$data);
        return back()->with('success','Financial operation recorded successfully.');
    }
}
