<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->string('command_center_conversation_id', 36)->nullable()->after('role');
            $table->string('command_center_mode', 20)->default('assist')->after('command_center_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn(['command_center_conversation_id', 'command_center_mode']);
        });
    }
};
