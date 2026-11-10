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
        Schema::table('site_assistants', function (Blueprint $table) {
            $table->boolean('talking_avatar_enabled')->default(false)->after('voice_name');
            $table->string('simli_face_id')->nullable()->after('talking_avatar_enabled');
            $table->string('simli_face_name')->nullable()->after('simli_face_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_assistants', function (Blueprint $table) {
            $table->dropColumn(['talking_avatar_enabled', 'simli_face_id', 'simli_face_name']);
        });
    }
};
