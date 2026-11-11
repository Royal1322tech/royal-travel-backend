<?php
namespace App\Modules\Identity\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class PermissionService {
 public function userCan(User $user,string $permission,string $companyId): bool {
  $membership=DB::table('rt_company_memberships as cm')->join('rt_companies as c','c.id','=','cm.company_id')->where('cm.user_id',$user->id)->where('cm.company_id',$companyId)->where('cm.status','active')->whereNotNull('cm.approved_at')->where('c.status','approved')->exists();
  if (!$membership) return false;
  return DB::table('rt_user_roles as ur')->join('rt_roles as r','r.id','=','ur.role_id')->join('rt_role_permissions as rp','rp.role_id','=','r.id')->join('rt_permissions as p','p.id','=','rp.permission_id')->where('ur.user_id',$user->id)->where('ur.company_id',$companyId)->where('r.portal','b2b')->where('p.code',$permission)->exists();
 }
 public function adminCan(User $user,string $permission): bool {
  if (!DB::table('rt_admin_access')->where('user_id',$user->id)->where('is_enabled',true)->exists()) return false;
  return DB::table('rt_user_roles as ur')->join('rt_roles as r','r.id','=','ur.role_id')->join('rt_role_permissions as rp','rp.role_id','=','r.id')->join('rt_permissions as p','p.id','=','rp.permission_id')->where('ur.user_id',$user->id)->whereNull('ur.company_id')->where('r.portal','admin')->where('p.code',$permission)->exists();
 }
}
