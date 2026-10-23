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
            $table->string('vibe')->nullable()->after('notes');
            $table->string('logo_path')->nullable()->after('vibe');
            $table->json('photo_paths')->nullable()->after('logo_path');
            $table->timestamp('details_confirmed_at')->nullable()->after('photo_paths');
            $table->string('site_generation_status')->default('idle')->after('details_confirmed_at');
            $table->text('site_generation_error')->nullable()->after('site_generation_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'vibe',
                'logo_path',
                'photo_paths',
                'details_confirmed_at',
                'site_generation_status',
                'site_generation_error',
            ]);
        });
    }
};
