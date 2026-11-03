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
         Schema::create('rt_company_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('rt_companies')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('full_name', 255);
            $table->string('phone', 30);
            $table->string('nationality', 100);

            // Store encrypted using Laravel Crypt.
            $table->text('id_number_encrypted');

            $table->string('contact_type', 50)
                ->default('primary');

            $table->timestampsTz();

            $table->unique(['company_id', 'user_id']);
            $table->index(['company_id', 'contact_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rt_company_contacts');
    }
};
