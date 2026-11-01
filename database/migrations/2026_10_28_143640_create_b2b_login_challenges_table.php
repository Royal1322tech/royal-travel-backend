<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('b2b_login_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('company_id')
                ->constrained('rt_companies')
                ->cascadeOnDelete();

            // Dashboard, website or mobile application.
            $table->string('channel', 20);

            // Store a cryptographic hash, never the OTP itself.
            $table->string('otp_hash', 255);

            $table->unsignedSmallInteger('attempts')
                ->default(0);

            $table->unsignedSmallInteger('max_attempts')
                ->default(5);

            $table->timestampTz('expires_at');

            $table->timestampTz('verified_at')
                ->nullable();

            $table->timestampTz('consumed_at')
                ->nullable();

            $table->timestampsTz();

            $table->index([
                'user_id',
                'company_id',
                'channel',
            ], 'b2b_challenges_user_company_channel_idx');

            $table->index('expires_at');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('b2b_login_challenges');
    }
};
