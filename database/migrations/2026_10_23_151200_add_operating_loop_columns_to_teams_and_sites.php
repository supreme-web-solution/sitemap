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
        Schema::table('teams', function (Blueprint $table) {
            $table->json('score_weights')->nullable();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->json('preview_section_views')->nullable();
            $table->json('delivery_findings')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('score_weights');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['preview_section_views', 'delivery_findings']);
        });
    }
};
