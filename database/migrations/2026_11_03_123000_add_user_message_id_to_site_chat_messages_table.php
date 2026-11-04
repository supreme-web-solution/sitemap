<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_chat_messages', function (Blueprint $table) {
            $table->foreignId('user_message_id')
                ->nullable()
                ->after('site_version_id')
                ->constrained('site_chat_messages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_message_id');
        });
    }
};
