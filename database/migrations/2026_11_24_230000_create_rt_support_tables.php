<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('rt_support_tickets', function(Blueprint $t){
   $t->uuid('id')->primary(); $t->string('ticket_no')->unique();
   $t->foreignUuid('booking_id')->constrained('rt_bookings');
   $t->foreignUuid('company_id')->constrained('rt_companies');
   $t->foreignId('created_by')->constrained('users');
   $t->string('category',40); $t->string('subject',150); $t->string('status',30)->default('open');
   $t->string('request_key',64); $t->string('request_hash',64); $t->timestamps();
   $t->unique(['company_id','created_by','request_key']); $t->index(['company_id','created_by','status']);
  });
  Schema::create('rt_support_ticket_messages', function(Blueprint $t){
   $t->uuid('id')->primary(); $t->foreignUuid('ticket_id')->constrained('rt_support_tickets');
   $t->foreignId('actor_user_id')->constrained('users'); $t->string('actor_type',20); $t->text('body'); $t->timestamps();
  });
  Schema::create('rt_support_ticket_events', function(Blueprint $t){
   $t->uuid('id')->primary(); $t->foreignUuid('ticket_id')->constrained('rt_support_tickets');
   $t->foreignId('actor_user_id')->constrained('users'); $t->string('from_status',30)->nullable(); $t->string('to_status',30); $t->timestamps();
  });
 }
 public function down(): void { throw new RuntimeException('Support data rollback disabled.'); }
};
