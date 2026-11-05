<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->timestamp('command_center_processing_at')->nullable()->after('command_center_mode');
            $table->string('command_center_whatsapp_link_code', 16)->nullable()->unique()->after('command_center_processing_at');
            $table->string('command_center_whatsapp_phone', 24)->nullable()->after('command_center_whatsapp_link_code');
            $table->timestamp('command_center_whatsapp_linked_at')->nullable()->after('command_center_whatsapp_phone');
            $table->string('command_center_whatsapp_conversation_id', 100)->nullable()->after('command_center_whatsapp_linked_at');
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn([
                'command_center_processing_at',
                'command_center_whatsapp_link_code',
                'command_center_whatsapp_phone',
                'command_center_whatsapp_linked_at',
                'command_center_whatsapp_conversation_id',
            ]);
        });
    }
};
