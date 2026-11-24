<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rt_booking_supplier_contacts', function (Blueprint $table): void {
            $table->uuid('booking_id')->primary();
            $table->text('email_encrypted');
            $table->text('phone_encrypted');
            $table->timestamps();
            $table->foreign('booking_id')->references('id')->on('rt_bookings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rt_booking_supplier_contacts');
    }
};
