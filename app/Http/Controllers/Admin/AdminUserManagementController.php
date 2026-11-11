<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
final class AdminUserManagementController extends Controller {
 private function requireSuperAdmin(Request $request): void {abort_unless(DB::table('rt_user_roles as ur')->join('rt_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$request->user()->id)->whereNull('ur.company_id')->where('r.portal','admin')->where('r.code','super_admin')->exists(),403);}
 private function adminRoles() {return DB::table('rt_roles')->where('portal','admin')->orderBy('code')->get();}
 private function audit(Request $r,string $action,string $target,array $details=[]): void {DB::table('rt_admin_audit_logs')->insert(['actor_user_id'=>$r->user()->id,'action'=>$action,'target_type'=>'user','target_id'=>$target,'details'=>json_encode($details),'created_at'=>now()]);}
 private function protectLastSuperAdmin(int $userId): void {
  $super=DB::table('rt_user_roles as ur')->join('rt_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->whereNull('ur.company_id')->where('r.portal','admin')->where('r.code','super_admin')->exists();
  if (!$super) return;
  $count=DB::table('rt_user_roles as ur')->join('rt_roles as r','r.id','=','ur.role_id')->join('rt_admin_access as a','a.user_id','=','ur.user_id')->whereNull('ur.company_id')->where('r.portal','admin')->where('r.code','super_admin')->where('a.is_enabled',true)->distinct()->count('ur.user_id');
  if ($count<=1) throw ValidationException::withMessages(['role_ids'=>'Cannot remove or disable the last enabled Super Admin.']);
 }
 private function assertRoles(array $ids): void {
  if (DB::table('rt_roles')->where('portal','admin')->whereIn('id',$ids)->count() !== count($ids)) throw ValidationException::withMessages(['role_ids'=>'Invalid Admin role selection.']);
 }
 public function index(Request $request) {
  $users=User::query()->whereExists(function($q){$q->selectRaw('1')->from('rt_user_roles as ur')->join('rt_roles as r','r.id','=','ur.role_id')->whereColumn('ur.user_id','users.id')->whereNull('ur.company_id')->where('r.portal','admin');})->when($request->filled('q'),fn($q)=>$q->where(fn($q)=>$q->where('name','ilike','%'.$request->q.'%')->orWhere('email','ilike','%'.$request->q.'%')))->orderBy('name')->paginate(25)->withQueryString();
  $access=DB::table('rt_admin_access')->whereIn('user_id',$users->pluck('id'))->pluck('is_enabled','user_id');
  $assigned=DB::table('rt_user_roles as ur')->join('rt_roles as r','r.id','=','ur.role_id')->whereIn('ur.user_id',$users->pluck('id'))->whereNull('ur.company_id')->where('r.portal','admin')->get(['ur.user_id','r.code'])->groupBy('user_id');
  return view('admin.access.users',compact('users','access','assigned'));
 }
 public function create(){return view('admin.access.user-form',['user'=>null,'roles'=>$this->adminRoles(),'selected'=>[]]);}
 public function edit(User $user){$selected=DB::table('rt_user_roles as ur')->join('rt_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$user->id)->whereNull('ur.company_id')->where('r.portal','admin')->pluck('r.id')->all();abort_unless(count($selected)>0,404);return view('admin.access.user-form',compact('user','selected')+['roles'=>$this->adminRoles()]);}
 public function store(Request $request){$this->requireSuperAdmin($request);
  $data=$request->validate(['name'=>['required','string','max:255'],'email'=>['required','email','max:255','unique:users,email'],'password'=>['required','string','min:12'],'role_ids'=>['required','array','min:1'],'role_ids.*'=>['required','uuid','distinct'],'is_enabled'=>['required','boolean']]);
  $this->assertRoles($data['role_ids']);
  DB::transaction(function()use($request,$data){$u=User::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>$data['password']]);DB::table('rt_admin_access')->insert(['user_id'=>$u->id,'is_enabled'=>(bool)$data['is_enabled'],'created_at'=>now(),'updated_at'=>now()]);foreach($data['role_ids'] as $id)DB::table('rt_user_roles')->insert(['id'=>(string)Str::uuid(),'user_id'=>$u->id,'role_id'=>$id,'company_id'=>null,'created_at'=>now()]);$this->audit($request,'admin_user.created',(string)$u->id,['roles'=>$data['role_ids']]);});
  return redirect()->route('admin.users.index')->with('success','Admin user created.');
 }
 public function update(Request $request,User $user){$this->requireSuperAdmin($request);
  $data=$request->validate(['name'=>['required','string','max:255'],'email'=>['required','email','max:255',Rule::unique('users','email')->ignore($user->id)],'role_ids'=>['required','array','min:1'],'role_ids.*'=>['required','uuid','distinct'],'is_enabled'=>['required','boolean']]);
  $this->assertRoles($data['role_ids']);
  $current=DB::table('rt_user_roles as ur')->join('rt_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$user->id)->whereNull('ur.company_id')->where('r.portal','admin')->pluck('r.id')->all();abort_unless(count($current)>0,404);
  $superId=DB::table('rt_roles')->where('portal','admin')->where('code','super_admin')->value('id');
  if ($superId && in_array($superId,$current,true) && (!(bool)$data['is_enabled'] || !in_array($superId,$data['role_ids'],true))) $this->protectLastSuperAdmin($user->id);
  if ($user->id===$request->user()->id && (!(bool)$data['is_enabled'] || ($superId && in_array($superId,$current,true) && !in_array($superId,$data['role_ids'],true)))) throw ValidationException::withMessages(['role_ids'=>'Cannot disable or demote your own Super Admin account.']);
  DB::transaction(function()use($request,$user,$data){$user->update(['name'=>$data['name'],'email'=>$data['email']]);DB::table('rt_admin_access')->updateOrInsert(['user_id'=>$user->id],['is_enabled'=>(bool)$data['is_enabled'],'updated_at'=>now()]);DB::table('rt_user_roles')->where('user_id',$user->id)->whereNull('company_id')->whereIn('role_id',DB::table('rt_roles')->where('portal','admin')->select('id'))->delete();foreach($data['role_ids'] as $id)DB::table('rt_user_roles')->insert(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'role_id'=>$id,'company_id'=>null,'created_at'=>now()]);$this->audit($request,'admin_user.updated',(string)$user->id,['roles'=>$data['role_ids'],'enabled'=>(bool)$data['is_enabled']]);});
  return redirect()->route('admin.users.index')->with('success','Admin user updated.');
 }
}
