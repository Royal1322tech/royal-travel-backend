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
  // Explicitly review and bootstrap admin access and grants before enabling this migration in any environment.
 }
 public function down(): void {throw new RuntimeException('Requires explicit rollback review.');}
};
