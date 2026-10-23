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
            $table->string('brand_name')->nullable();
            $table->string('brand_logo_url', 500)->nullable();
            $table->string('brand_color', 7)->nullable();
            $table->string('brand_email')->nullable();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('portal_token', 40)->nullable()->unique();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('portal_token');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['brand_name', 'brand_logo_url', 'brand_color', 'brand_email']);
        });
    }
};