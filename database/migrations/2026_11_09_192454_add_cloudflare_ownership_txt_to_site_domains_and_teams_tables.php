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
        Schema::table('site_domains', function (Blueprint $table) {
            $table->string('cloudflare_ownership_txt_name')->nullable()->after('cloudflare_ssl_status');
            $table->string('cloudflare_ownership_txt_value')->nullable()->after('cloudflare_ownership_txt_name');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('preview_cloudflare_ownership_txt_name')->nullable()->after('preview_cloudflare_ssl_status');
            $table->string('preview_cloudflare_ownership_txt_value')->nullable()->after('preview_cloudflare_ownership_txt_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_domains', function (Blueprint $table) {
            $table->dropColumn([
                'cloudflare_ownership_txt_name',
                'cloudflare_ownership_txt_value',
            ]);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'preview_cloudflare_ownership_txt_name',
                'preview_cloudflare_ownership_txt_value',
            ]);
        });
    }
};
