<?php
namespace App\Http\Controllers\Api\V1\B2B\Bookings;

use App\Http\Controllers\Controller;
use App\Services\Hotels\RateHawkBookingOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class BookingConfirmationController extends Controller
{
    private function booking(Request $request, string $id): object
    {
        $company = $request->attributes->get('authenticated_company_id');
        abort_unless(is_string($company) && Str::isUuid($company),403);
        abort_unless(Str::isUuid($id),404);
        return DB::table('rt_bookings')->where('id',$id)->where('company_id',$company)->where('channel','b2b')->where('service_type','hotel')->first() ?? abort(404);
    }
    private function attempt(string $id): ?object
    {
        return DB::table('rt_supplier_booking_attempts')->where('booking_id',$id)->where('operation','ratehawk_hotel_booking')->first();
    }
    public function status(Request $request, string $booking)
    {
        $b=$this->booking($request,$booking); $a=$this->attempt($booking);
        return response()->json(['booking_id'=>$booking,'status'=>$b->status,'confirmation_state'=>$a->state ?? null,'confirmed_at'=>$b->confirmed_at])->header('Cache-Control','no-store');
    }
    public function confirm(Request $request, string $booking, RateHawkBookingOrchestrator $flow)
    {
        $this->booking($request,$booking);
        abort_unless(config('services.ratehawk.environment')==='sandbox' && config('services.ratehawk.allow_sandbox_finish')===true,403,'Sandbox confirmation disabled.');
        $request->validate(['accept_price'=>['required','accepted']]);
        $lock=Cache::lock('b2b-confirm:'.$booking,300);
        abort_unless($lock->get(),409,'Confirmation in progress.');
        try {
            $b=$this->booking($request,$booking); $a=$this->attempt($booking);
            if ($b->status==='confirmed') return $this->status($request,$booking);
            if ($a && in_array($a->state,['finish_unknown','processing'],true)) {
                $flow->reconcile($booking); return $this->status($request,$booking);
            }
            abort_unless($b->status==='draft' && (!$a || in_array($a->state,['prepared','form_ready'],true)),409,'Manual review required.');
            $offer=DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id',$booking)->first();
            $price=DB::table('rt_booking_price_snapshots')->where('booking_id',$booking)->orderByDesc('version')->first();
            $hotel=DB::table('rt_hotel_booking_details')->where('booking_id',$booking)->first();
            $contact=DB::table('rt_booking_supplier_contacts')->where('booking_id',$booking)->first();
            abort_unless($offer && $price && $hotel && $contact && $offer->environment==='sandbox' && $b->currency==='USD' && $price->currency==='USD' && $offer->currency==='USD' && $offer->prebook_hash===$hotel->supplier_offer_ref && str_starts_with($offer->prebook_hash,'p-') && $price->supplier_cost!==null && $price->admin_markup!==null && $b->approved_total!==null && bccomp($offer->supplier_cost,$price->supplier_cost,6)===0 && bccomp(bcadd($price->supplier_cost,$price->admin_markup,6),$b->approved_total,6)===0,409,'Invalid booking context.');
            $at=\Illuminate\Support\Carbon::parse($offer->prebook_verified_at);
            abort_if($at->isFuture() || $at->lte(now()->subMinutes(10)),410,'Prebook expired.');
            abort_unless(DB::table('rt_suppliers')->where('id',$b->supplier_id)->where('code','RATEHAWK')->where('adapter_code','RATEHAWK')->where('status','active')->exists(),409,'Supplier unavailable.');
            $email=Crypt::decryptString($contact->email_encrypted); $phone=Crypt::decryptString($contact->phone_encrypted);
            $rooms=[];
            foreach(DB::table('rt_booking_guests')->where('booking_id',$booking)->orderBy('room_index')->get() as $g) {
                abort_unless($g->room_index!==null,409,'Missing room assignment.');
                $guest=['first_name'=>$g->first_name,'last_name'=>$g->last_name,'is_child'=>$g->guest_type==='child'];
                if ($guest['is_child']) { abort_unless($g->age!==null,409,'Missing child age.'); $guest['age']=(int)$g->age; }
                if ($g->gender!==null) $guest['gender']=$g->gender;
                $rooms[(int)$g->room_index]['guests'][]=$guest;
            }
            abort_unless(count($rooms)===(int)$hotel->rooms_count && array_keys($rooms)===range(0,count($rooms)-1),409,'Invalid rooms.');
            if (!$a) { $flow->prepare($booking); $a=$this->attempt($booking); }
            if ($a->state==='prepared') { $form=$flow->createForm($booking,$offer->prebook_hash,$request->ip()); }
            else { $stored=DB::table('rt_ratehawk_booking_forms')->where('attempt_id',$a->id)->first(); abort_unless($stored,409,'Manual form recovery required.'); $form=json_decode(Crypt::decryptString($stored->response_encrypted),true,512,JSON_THROW_ON_ERROR); }
            abort_if($at->lte(now()->subMinutes(10)),410,'Prebook expired; manual review required.');
            $flow->startBooking($booking,$form,$rooms,$email,$phone);
            $flow->reconcile($booking);
            return $this->status($request,$booking);
        } catch (\RuntimeException $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) throw $e;
            report($e);
            return response()->json(['booking_id'=>$booking,'confirmation_state'=>$this->attempt($booking)->state ?? null,'message'=>'Confirmation not completed. Check status; manual review may be required.'],409);
        } finally { $lock->release(); }
    }
}
