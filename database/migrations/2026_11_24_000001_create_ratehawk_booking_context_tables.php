<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rt_ratehawk_booking_offer_contexts', function (Blueprint $table): void {
            $table->uuid('booking_id')->primary();
            $table->string('environment', 32);
            $table->unsignedBigInteger('hid');
            $table->text('hotelpage_book_hash');
            $table->text('prebook_hash');
            $table->timestampTz('prebook_verified_at');
            $table->string('currency', 3);
            $table->decimal('supplier_cost', 18, 6);
            $table->boolean('price_changed');
            $table->timestampsTz();
            $table->foreign('booking_id')->references('id')->on('rt_bookings')->cascadeOnDelete();
        });

        Schema::create('rt_ratehawk_booking_forms', function (Blueprint $table): void {
            $table->uuid('attempt_id')->primary();
            $table->text('response_encrypted');
            $table->timestampTz('received_at');
            $table->timestampsTz();
            $table->foreign('attempt_id')->references('id')->on('rt_supplier_booking_attempts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rt_ratehawk_booking_forms');
        Schema::dropIfExists('rt_ratehawk_booking_offer_contexts');
    }
};
