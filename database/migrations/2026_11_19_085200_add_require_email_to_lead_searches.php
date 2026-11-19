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
            $table->boolean('require_email')->default(false)->after('max_results');
        });

        Schema::table('saved_lead_searches', function (Blueprint $table) {
            $table->boolean('require_email')->default(false)->after('max_results');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_searches', function (Blueprint $table) {
            $table->dropColumn('require_email');
        });

        Schema::table('saved_lead_searches', function (Blueprint $table) {
            $table->dropColumn('require_email');
        });
    }
};
