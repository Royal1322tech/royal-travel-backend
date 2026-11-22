<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\Company;
use App\Services\Finance\B2BFinanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class B2BFinanceController extends Controller {
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
