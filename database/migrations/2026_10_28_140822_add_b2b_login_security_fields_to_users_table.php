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
        Schema::table('users', function (Blueprint $table) {

            // True only for accounts that must change
            // their temporary password.
            $table->boolean('must_change_password')
                ->default(false);

            // Expiration date of the temporary password.
            $table->timestampTz('temporary_password_expires_at')
                ->nullable();

            // Date of the last password change.
            $table->timestampTz('password_changed_at')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
     public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'must_change_password',
                'temporary_password_expires_at',
                'password_changed_at',
            ]);
        });
    }
};
