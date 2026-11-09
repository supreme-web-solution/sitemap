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
            $table->string('cloudflare_hostname_id', 36)->nullable()->after('check_error');
            $table->string('cloudflare_status', 40)->nullable()->after('cloudflare_hostname_id');
            $table->string('cloudflare_ssl_status', 40)->nullable()->after('cloudflare_status');
            $table->timestamp('cloudflare_synced_at')->nullable()->after('cloudflare_ssl_status');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('preview_cloudflare_hostname_id', 36)->nullable()->after('preview_hostname_check_error');
            $table->string('preview_cloudflare_status', 40)->nullable()->after('preview_cloudflare_hostname_id');
            $table->string('preview_cloudflare_ssl_status', 40)->nullable()->after('preview_cloudflare_status');
            $table->timestamp('preview_cloudflare_synced_at')->nullable()->after('preview_cloudflare_ssl_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_domains', function (Blueprint $table) {
            $table->dropColumn([
                'cloudflare_hostname_id',
                'cloudflare_status',
                'cloudflare_ssl_status',
                'cloudflare_synced_at',
            ]);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'preview_cloudflare_hostname_id',
                'preview_cloudflare_status',
                'preview_cloudflare_ssl_status',
                'preview_cloudflare_synced_at',
            ]);
        });
    }
};
