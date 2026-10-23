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
            $table->string('lost_reason', 30)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('reviews_summary')->nullable();
            $table->json('competitor_audit')->nullable();
            $table->timestamp('competitor_audited_at')->nullable();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('preview_expires_at')->nullable();
            $table->string('preview_password')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->string('transferred_to_email')->nullable();
            $table->timestamp('last_uptime_at')->nullable();
            $table->boolean('uptime_ok')->nullable();
            $table->timestamp('last_crawled_at')->nullable();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->json('ideal_customer')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('kind', 20)->default('standard');
            $table->timestamp('refunded_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn(['lost_reason', 'reviews_summary', 'competitor_audit', 'competitor_audited_at']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'preview_expires_at',
                'preview_password',
                'transferred_at',
                'transferred_to_email',
                'last_uptime_at',
                'uptime_ok',
                'last_crawled_at',
            ]);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('ideal_customer');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['kind', 'refunded_at']);
        });
    }
};
