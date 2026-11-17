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
        Schema::table('rt_hotels', function (Blueprint $table) {
            $table->smallInteger('star_rating')->nullable();
            $table->text('image_url')->nullable();
            $table->string('hotel_kind', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rt_hotels', function (Blueprint $table) {
            $table->dropColumn([
                'star_rating',
                'image_url',
                'hotel_kind',
            ]);
        });
    }
};
