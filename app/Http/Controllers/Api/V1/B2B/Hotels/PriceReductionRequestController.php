<?php

namespace App\Http\Controllers\Api\V1\B2B\Hotels;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class PriceReductionRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('authenticated_company_id');
        abort_unless(is_string($companyId) && Str::isUuid($companyId), 403);
        $data = $request->validate([
            'booking_id' => ['nullable', 'uuid'],
            'supplier_hid' => ['required','integer','min:1'],
            'hotel_name' => ['required','string','max:255'],
            'hotel_address' => ['nullable','string','max:500'],
            'room_name' => ['required','string','max:255'],
            'checkin' => ['required','date_format:Y-m-d'],
            'checkout' => ['required','date_format:Y-m-d','after:checkin'],
            'adults' => ['required','integer','between:1,54'],
            'children' => ['sometimes','integer','between:0,36'],
            'currency' => ['required',Rule::in(['USD','EGP','SAR'])],
            'displayed_price' => ['nullable','numeric','gt:0'],
            'requested_price' => ['required','numeric','gt:0'],
            'notes' => ['nullable','string','max:2000'],
            'attachment' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:5120'],
        ]);
        // A linked booking must belong to this exact user and tenant.
        if (!empty($data['booking_id'])) {
            abort_unless(DB::table('rt_bookings')->where('id',$data['booking_id'])
                ->where('company_id',$companyId)->where('customer_user_id',$request->user()->id)
                ->where('service_type','hotel')->exists(), 422, 'Invalid booking reference.');
        }
        $path = null;
        try {
            if ($request->hasFile('attachment')) {
                $path = $request->file('attachment')->store('price-reduction-requests', 'local');
                if ($path === false) throw new \RuntimeException('Attachment storage failed.');
            }
            $id = (string) Str::uuid();
            $no = 'RP-' . strtoupper(substr(str_replace('-','',$id),0,12));
            DB::transaction(function () use ($request,$data,$companyId,$path,$id,$no): void {
                DB::table('rt_price_reduction_requests')->insert([
                    'id'=>$id,'request_no'=>$no,'channel'=>'b2b','company_id'=>$companyId,
                    'customer_user_id'=>$request->user()->id,'booking_id'=>$data['booking_id']??null,
                    'supplier_hid'=>$data['supplier_hid'],'hotel_name'=>$data['hotel_name'],
                    'hotel_address'=>$data['hotel_address']??null,'room_name'=>$data['room_name'],
                    'checkin'=>$data['checkin'],'checkout'=>$data['checkout'],
                    'adults'=>$data['adults'],'children'=>$data['children']??0,
                    'currency'=>$data['currency'],'displayed_price'=>$data['displayed_price']??null,
                    'requested_price'=>$data['requested_price'],'notes'=>$data['notes']??null,
                    'attachment_path'=>$path,
                    'attachment_original_name'=>$request->file('attachment')?->getClientOriginalName(),
                    'attachment_mime'=>$request->file('attachment')?->getMimeType(),
                    'status'=>'new','created_at'=>now(),'updated_at'=>now(),
                ]);
                DB::table('rt_price_reduction_request_events')->insert([
                    'request_id'=>$id,'actor_user_id'=>$request->user()->id,
                    'to_status'=>'new','created_at'=>now(),
                ]);
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        }
        return response()->json(['success'=>true,'message'=>'Request submitted for review.',
            'data'=>['id'=>$id,'request_no'=>$no,'status'=>'new']],201)->header('Cache-Control','no-store');
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('authenticated_company_id');
        abort_unless(is_string($companyId) && Str::isUuid($companyId), 403);
        $rows = DB::table('rt_price_reduction_requests')
            ->where('company_id',$companyId)->where('customer_user_id',$request->user()->id)
            ->select('id','request_no','hotel_name','room_name','checkin','checkout','currency',
                'requested_price','status','created_at')->orderByDesc('created_at')->paginate(15);
        return response()->json(['success'=>true,'data'=>$rows])->header('Cache-Control','no-store');
    }
}
