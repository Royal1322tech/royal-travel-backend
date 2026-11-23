<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('rt_customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('rt_companies')->restrictOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 254)->nullable();
            $table->string('phone', 35)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['company_id', 'last_name']);
            $table->index(['company_id', 'email']);
        });
        Schema::table('rt_orders', function (Blueprint $table): void {
            $table->uuid('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('rt_customers')->restrictOnDelete();
        });
        Schema::table('rt_bookings', function (Blueprint $table): void {
            $table->uuid('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('rt_customers')->restrictOnDelete();
        });
    }
    public function down(): void {
        Schema::table('rt_bookings', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::table('rt_orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::dropIfExists('rt_customers');
    }
};
