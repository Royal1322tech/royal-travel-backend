<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class PriceReductionRequestController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['status'=>['nullable',Rule::in(['new','in_review','approved','rejected','replied','completed'])],
            'q'=>['nullable','string','max:100']]);
        $query = DB::table('rt_price_reduction_requests as r')
            ->join('users as u','u.id','=','r.customer_user_id')
            ->select('r.*','u.name as customer_name','u.email as customer_email');
        if (!empty($filters['status'])) $query->where('r.status',$filters['status']);
        if (!empty($filters['q'])) {
            $q = str_replace(['%','_'],['\%','\_'],$filters['q']);
            $query->where(function($builder) use ($q) {
                $builder->where('r.request_no','ilike','%'.$q.'%')
                    ->orWhere('r.hotel_name','ilike','%'.$q.'%')
                    ->orWhere('u.name','ilike','%'.$q.'%');
            });
        }
        return view('admin.price-reduction.index',[
            'requests'=>$query->orderByDesc('r.created_at')->paginate(20)->withQueryString(),
            'selectedStatus'=>$filters['status']??'', 'search'=>$filters['q']??'',
            'counts'=>DB::table('rt_price_reduction_requests')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total','status'),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'status'=>['required',Rule::in(['in_review','approved','rejected','replied','completed'])],
            'admin_note'=>['nullable','string','max:2000'],
        ]);
        DB::transaction(function() use ($request,$data,$id): void {
            $current = DB::table('rt_price_reduction_requests')->where('id',$id)->lockForUpdate()->first();
            abort_unless($current,404);
            $allowed = [
                'new'=>['in_review','approved','rejected'],
                'in_review'=>['approved','rejected','replied'],
                'approved'=>['replied','rejected'],
                'replied'=>['completed'],
                'rejected'=>[], 'completed'=>[],
            ];
            abort_unless(in_array($data['status'],$allowed[$current->status]??[],true),422,'Invalid status transition.');
            DB::table('rt_price_reduction_requests')->where('id',$id)->update([
                'status'=>$data['status'],'admin_note'=>$data['admin_note']??null,
                'reviewed_by'=>$request->user()->id,'reviewed_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('rt_price_reduction_request_events')->insert([
                'request_id'=>$id,'actor_user_id'=>$request->user()->id,
                'from_status'=>$current->status,'to_status'=>$data['status'],
                'note'=>$data['admin_note']??null,'created_at'=>now(),
            ]);
        });
        return redirect()->route('admin.price-reduction.index')->with('success','Request updated.');
    }

    public function attachment(string $id)
    {
        $row = DB::table('rt_price_reduction_requests')->where('id',$id)->first();
        abort_unless($row && $row->attachment_path,404);
        abort_unless(Storage::disk('local')->exists($row->attachment_path),404);
        return Storage::disk('local')->download($row->attachment_path,
            $row->attachment_original_name ?: 'supporting-document',
            ['X-Content-Type-Options'=>'nosniff']);
    }
}
