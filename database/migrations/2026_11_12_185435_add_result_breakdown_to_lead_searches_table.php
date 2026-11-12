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
        Schema::table('lead_searches', function (Blueprint $table) {
            $table->unsignedInteger('leads_created_count')->default(0)->after('results_count');
            $table->unsignedInteger('leads_updated_count')->default(0)->after('leads_created_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_searches', function (Blueprint $table) {
            $table->dropColumn(['leads_created_count', 'leads_updated_count']);
        });
    }
};
