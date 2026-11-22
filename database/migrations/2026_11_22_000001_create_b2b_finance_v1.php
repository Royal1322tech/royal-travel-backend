<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('rt_b2b_finance_accounts', function (Blueprint $t) {
            $t->uuid('company_id')->primary();
            $t->string('currency', 3)->default('USD');
            $t->bigInteger('wallet_cents')->default(0);
            $t->bigInteger('credit_limit_cents')->default(0);
            $t->bigInteger('credit_used_cents')->default(0);
            $t->boolean('credit_frozen')->default(false);
            $t->timestampsTz();
            $t->foreign('company_id')->references('id')->on('rt_companies');
        });
        Schema::create('rt_b2b_finance_entries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->string('type', 40);
            $t->bigInteger('amount_cents')->nullable();
            $t->bigInteger('wallet_after_cents');
            $t->bigInteger('limit_after_cents');
            $t->bigInteger('used_after_cents');
            $t->boolean('frozen_after');
            $t->string('reference', 100);
            $t->text('reason');
            $t->uuid('actor_id');
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['company_id','reference']);
            $t->index(['company_id','created_at']);
            $t->foreign('company_id')->references('id')->on('rt_companies');
        });
    }
    public function down(): void {
        Schema::dropIfExists('rt_b2b_finance_entries');
        Schema::dropIfExists('rt_b2b_finance_accounts');
    }
};
