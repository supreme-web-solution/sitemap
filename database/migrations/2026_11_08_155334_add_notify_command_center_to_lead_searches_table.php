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
            $table->boolean('notify_command_center')->default(false)->after('error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_searches', function (Blueprint $table) {
            $table->dropColumn('notify_command_center');
        });
    }
};
