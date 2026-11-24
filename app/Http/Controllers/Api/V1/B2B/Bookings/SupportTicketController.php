<?php
namespace App\Http\Controllers\Api\V1\B2B\Bookings;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
final class SupportTicketController extends Controller {
 public const CATEGORIES=['booking_issue','hotel_issue','checkin_issue','room_issue','payment_issue','voucher_issue','cancellation_issue','refund_issue','other'];
 private function scope(Request $r) { $c=$r->attributes->get('authenticated_company_id'); abort_unless($r->user() && is_string($c) && Str::isUuid($c),403); return DB::table('rt_support_tickets')->where('company_id',$c)->where('created_by',$r->user()->id); }
 private function booking(Request $r,string $id) { $this->scope($r); $c=$r->attributes->get('authenticated_company_id'); $b=DB::table('rt_bookings as b')->join('rt_orders as o','o.id','=','b.order_id')->where('b.id',$id)->where('b.company_id',$c)->where('o.company_id',$c)->where('b.channel','b2b')->where('o.channel','b2b')->where('o.created_by',$r->user()->id)->select('b.*')->first(); abort_unless($b,404); return $b; }
 private function present($t) { return collect($t)->except(['request_key','request_hash'])->all(); }
 public function categories(){return response()->json(self::CATEGORIES);}
 public function index(Request $r){return response()->json($this->scope($r)->orderByDesc('created_at')->paginate(20)->through(fn($t)=>$this->present($t)))->header('Cache-Control','no-store');}
 public function forBooking(Request $r,string $booking){$this->booking($r,$booking);return response()->json($this->scope($r)->where('booking_id',$booking)->orderByDesc('created_at')->paginate(20)->through(fn($t)=>$this->present($t)))->header('Cache-Control','no-store');}
 public function store(Request $r,string $booking){
  $b=$this->booking($r,$booking); abort_unless($b->confirmed_at && in_array($b->status,['confirmed','completed','cancelled'],true),409,'Booking must have been confirmed.');
  $d=$r->validate(['category'=>['required',Rule::in(self::CATEGORIES)],'subject'=>'required|string|max:150','description'=>'required|string|max:5000']);
  $key=$r->header('Idempotency-Key'); abort_unless(is_string($key)&&preg_match('/^[A-Za-z0-9_-]{16,128}$/D',$key),422,'Idempotency-Key required.');
  $hash=hash('sha256',json_encode([$booking,$d],JSON_THROW_ON_ERROR)); $key=hash('sha256',$key);
  return DB::transaction(function()use($r,$b,$d,$key,$hash){
   $id=(string)Str::uuid(); $now=now();
   $created=DB::table('rt_support_tickets')->insertOrIgnore(['id'=>$id,'ticket_no'=>'TKT-'.strtoupper(Str::random(16)),'booking_id'=>$b->id,'company_id'=>$b->company_id,'created_by'=>$r->user()->id,'category'=>$d['category'],'subject'=>$d['subject'],'status'=>'open','request_key'=>$key,'request_hash'=>$hash,'created_at'=>$now,'updated_at'=>$now]);
   $t=$this->scope($r)->where('request_key',$key)->lockForUpdate()->first(); abort_unless($t && hash_equals($t->request_hash,$hash),409,'Idempotency conflict.');
   if($created){DB::table('rt_support_ticket_messages')->insert(['id'=>(string)Str::uuid(),'ticket_id'=>$id,'actor_user_id'=>$r->user()->id,'actor_type'=>'employee','body'=>$d['description'],'created_at'=>$now,'updated_at'=>$now]);}
   return response()->json($this->present($t),$created?201:200)->header('Cache-Control','no-store');
  });
 }
 public function show(Request $r,string $ticket){$t=$this->scope($r)->where('id',$ticket)->first();abort_unless($t,404);return response()->json(['ticket'=>$this->present($t),'messages'=>DB::table('rt_support_ticket_messages')->where('ticket_id',$ticket)->select('id','actor_type','body','created_at')->orderBy('created_at')->orderBy('id')->paginate(50)])->header('Cache-Control','no-store');}
 public function reply(Request $r,string $ticket){$d=$r->validate(['message'=>'required|string|max:5000']);return DB::transaction(function()use($r,$ticket,$d){$t=$this->scope($r)->where('id',$ticket)->lockForUpdate()->first();abort_unless($t,404);abort_if(in_array($t->status,['resolved','closed']),409);DB::table('rt_support_ticket_messages')->insert(['id'=>(string)Str::uuid(),'ticket_id'=>$ticket,'actor_user_id'=>$r->user()->id,'actor_type'=>'employee','body'=>$d['message'],'created_at'=>now(),'updated_at'=>now()]);return response()->json(['status'=>'sent'],201);});}
}
