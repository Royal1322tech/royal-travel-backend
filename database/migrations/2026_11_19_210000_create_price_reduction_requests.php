<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        if (DB::getDriverName() !== 'pgsql') throw new RuntimeException('PostgreSQL required.');
        DB::statement("CREATE TABLE rt_price_reduction_requests (
            id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
            request_no varchar(40) NOT NULL UNIQUE,
            channel varchar(8) NOT NULL CHECK(channel IN ('b2b','b2c')),
            company_id uuid REFERENCES rt_companies(id) ON DELETE RESTRICT,
            customer_user_id bigint NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            booking_id uuid REFERENCES rt_bookings(id) ON DELETE RESTRICT,
            supplier_hid bigint NOT NULL CHECK(supplier_hid > 0),
            hotel_name varchar(255) NOT NULL,
            hotel_address varchar(500),
            room_name varchar(255) NOT NULL,
            checkin date NOT NULL, checkout date NOT NULL,
            adults smallint NOT NULL CHECK(adults > 0),
            children smallint NOT NULL DEFAULT 0 CHECK(children >= 0),
            currency char(3) NOT NULL,
            displayed_price numeric(20,6),
            requested_price numeric(20,6) NOT NULL CHECK(requested_price > 0),
            notes text,
            attachment_path text,
            attachment_original_name varchar(255),
            attachment_mime varchar(100),
            status varchar(24) NOT NULL DEFAULT 'new' CHECK(status IN ('new','in_review','approved','rejected','replied','completed')),
            admin_note text,
            reviewed_by bigint REFERENCES users(id) ON DELETE RESTRICT,
            reviewed_at timestamptz,
            created_at timestamptz NOT NULL DEFAULT now(),
            updated_at timestamptz NOT NULL DEFAULT now(),
            CHECK(checkout > checkin),
            CHECK((channel='b2b' AND company_id IS NOT NULL) OR (channel='b2c' AND company_id IS NULL))
        )");
        DB::statement('CREATE INDEX rt_price_requests_customer_idx ON rt_price_reduction_requests(customer_user_id, created_at DESC)');
        DB::statement('CREATE INDEX rt_price_requests_status_idx ON rt_price_reduction_requests(status, created_at DESC)');
        DB::statement("CREATE TABLE rt_price_reduction_request_events (
            id bigserial PRIMARY KEY,
            request_id uuid NOT NULL REFERENCES rt_price_reduction_requests(id) ON DELETE RESTRICT,
            actor_user_id bigint NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            from_status varchar(24), to_status varchar(24) NOT NULL,
            note text,
            created_at timestamptz NOT NULL DEFAULT now()
        )");
        foreach (['price_requests.view','price_requests.manage'] as $code) {
            DB::table('rt_permissions')->updateOrInsert(['code'=>$code],['description'=>$code]);
            $permissionId = DB::table('rt_permissions')->where('code',$code)->value('id');
            $adminRoleIds = DB::table('rt_roles')->where('portal','admin')->where('code','super_admin')->pluck('id');
            foreach ($adminRoleIds as $roleId) {
                DB::table('rt_role_permissions')->updateOrInsert(['role_id'=>$roleId,'permission_id'=>$permissionId],[]);
            }
        }
    }
    public function down(): void { throw new RuntimeException('Rollback disabled for Royal data.'); }
};
