<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('rt_admin_access',function(Blueprint $t){$t->foreignId('user_id')->primary()->constrained('users')->restrictOnDelete();$t->boolean('is_enabled')->default(false);$t->timestampsTz();});
  Schema::create('rt_admin_audit_logs',function(Blueprint $t){$t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));$t->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();$t->string('action',120);$t->string('target_type',80);$t->string('target_id',120);$t->jsonb('details')->nullable();$t->timestampTz('created_at')->useCurrent();});
  foreach (['users.view'=>'View admin users','users.manage'=>'Manage admin users','roles.view'=>'View admin roles','roles.manage'=>'Manage admin roles'] as $code=>$description) DB::table('rt_permissions')->updateOrInsert(['code'=>$code],['description'=>$description]);
  // Preserve access for existing authorized super administrators during upgrade.
  $admins = DB::table('rt_user_roles as ur')
   ->join('rt_roles as r', 'r.id', '=', 'ur.role_id')
   ->join('rt_role_permissions as rp', 'rp.role_id', '=', 'r.id')
   ->join('rt_permissions as p', 'p.id', '=', 'rp.permission_id')
   ->whereNull('ur.company_id')
   ->where('r.portal', 'admin')
   ->where('r.code', 'super_admin')
   ->where('p.code', 'dashboard.view')
   ->distinct()
   ->pluck('ur.user_id');
  foreach ($admins as $userId) {
   DB::table('rt_admin_access')->insert([
    'user_id' => $userId,
    'is_enabled' => true,
    'created_at' => now(),
    'updated_at' => now(),
   ]);
  }
 }
 public function down(): void {throw new RuntimeException('Requires explicit rollback review.');}
};
