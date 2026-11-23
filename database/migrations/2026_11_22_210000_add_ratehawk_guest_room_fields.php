<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rt_booking_guests', function (Blueprint $table): void {
            $table->unsignedSmallInteger('room_index')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('gender', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rt_booking_guests', function (Blueprint $table): void {
            $table->dropColumn(['room_index', 'age', 'gender']);
        });
    }
};
