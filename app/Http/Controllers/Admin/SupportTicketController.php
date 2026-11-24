<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
final class SupportTicketController extends Controller {
 public const STATES=['open','in_progress','waiting_for_customer','resolved','closed'];
 private function query(){return DB::table('rt_support_tickets as t')->join('rt_bookings as b','b.id','=','t.booking_id')->join('users as u','u.id','=','t.created_by')->select('t.*','b.booking_no','u.name as employee_name','u.email as employee_email');}
 public function index(Request $r){$d=$r->validate(['status'=>['nullable',Rule::in(self::STATES)]]);$q=$this->query();if(!empty($d['status']))$q->where('t.status',$d['status']);return view('admin.support.index',['tickets'=>$q->orderByDesc('t.created_at')->paginate(20)->withQueryString(),'states'=>self::STATES]);}
 public function show(string $ticket){$t=$this->query()->where('t.id',$ticket)->first();abort_unless($t,404);return view('admin.support.show',['ticket'=>$t,'messages'=>DB::table('rt_support_ticket_messages')->where('ticket_id',$ticket)->orderBy('created_at')->paginate(50),'states'=>self::STATES]);}
 public function update(Request $r,string $ticket){$d=$r->validate(['status'=>['required',Rule::in(self::STATES)],'message'=>'nullable|string|max:5000']);DB::transaction(function()use($r,$ticket,$d){$t=DB::table('rt_support_tickets')->where('id',$ticket)->lockForUpdate()->first();abort_unless($t,404);$allowed=['open'=>['in_progress','waiting_for_customer','resolved'],'in_progress'=>['waiting_for_customer','resolved'],'waiting_for_customer'=>['in_progress','resolved'],'resolved'=>['closed','in_progress'],'closed'=>[]];abort_unless($d['status']===$t->status || in_array($d['status'],$allowed[$t->status]??[],true),422);abort_if($t->status==='closed',409);if(!empty($d['message']))DB::table('rt_support_ticket_messages')->insert(['id'=>(string)Str::uuid(),'ticket_id'=>$ticket,'actor_user_id'=>$r->user()->id,'actor_type'=>'support','body'=>$d['message'],'created_at'=>now(),'updated_at'=>now()]);if($t->status!==$d['status'])DB::table('rt_support_ticket_events')->insert(['id'=>(string)Str::uuid(),'ticket_id'=>$ticket,'actor_user_id'=>$r->user()->id,'from_status'=>$t->status,'to_status'=>$d['status'],'created_at'=>now(),'updated_at'=>now()]);DB::table('rt_support_tickets')->where('id',$ticket)->update(['status'=>$d['status'],'updated_at'=>now()]);});return back()->with('success','Ticket updated.');}
}
