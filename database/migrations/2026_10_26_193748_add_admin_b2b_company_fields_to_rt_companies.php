<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rt_companies', function (Blueprint $table) {
            $table->string('business_type', 30)->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->string('city', 150)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rt_companies', function (Blueprint $table) {
            $table->dropColumn([
                'business_type',
                'employee_count',
                'city',
                'address',
                'created_by',
            ]);
        });
    }
};
