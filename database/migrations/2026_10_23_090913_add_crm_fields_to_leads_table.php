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
            $table->date('next_follow_up_at')->nullable()->after('notes');
            $table->timestamp('last_contacted_at')->nullable()->after('next_follow_up_at');
            $table->unsignedTinyInteger('opportunity_score')->nullable()->after('last_contacted_at');
            $table->json('opportunity_breakdown')->nullable()->after('opportunity_score');
            $table->json('website_audit')->nullable()->after('opportunity_breakdown');
            $table->timestamp('website_audited_at')->nullable()->after('website_audit');
            $table->string('website_audit_status')->default('idle')->after('website_audited_at');

            $table->index(['team_id', 'next_follow_up_at']);
            $table->index(['team_id', 'opportunity_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'next_follow_up_at']);
            $table->dropIndex(['team_id', 'opportunity_score']);
            $table->dropColumn([
                'next_follow_up_at',
                'last_contacted_at',
                'opportunity_score',
                'opportunity_breakdown',
                'website_audit',
                'website_audited_at',
                'website_audit_status',
            ]);
        });
    }
};
