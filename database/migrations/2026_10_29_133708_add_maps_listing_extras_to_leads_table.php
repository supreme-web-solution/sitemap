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
        Schema::table('leads', function (Blueprint $table) {
            $table->json('listing_services')->nullable()->after('description');
            $table->json('listing_photo_urls')->nullable()->after('photo_url');
            $table->string('neighborhood')->nullable()->after('city');
            $table->string('located_in')->nullable()->after('neighborhood');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'listing_services',
                'listing_photo_urls',
                'neighborhood',
                'located_in',
            ]);
        });
    }
};
