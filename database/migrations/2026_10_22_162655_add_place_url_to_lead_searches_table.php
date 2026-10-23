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
            $table->text('place_url')->nullable()->after('location');
            $table->string('keyword')->nullable()->change();
            $table->string('location')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_searches', function (Blueprint $table) {
            $table->dropColumn('place_url');
        });
    }
};
