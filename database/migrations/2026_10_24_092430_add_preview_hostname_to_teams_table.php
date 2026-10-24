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
        Schema::table('teams', function (Blueprint $table) {
            $table->string('preview_hostname', 253)->nullable()->unique();
            $table->string('preview_hostname_token', 64)->nullable();
            $table->timestamp('preview_hostname_verified_at')->nullable();
            $table->timestamp('preview_hostname_checked_at')->nullable();
            $table->string('preview_hostname_check_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'preview_hostname',
                'preview_hostname_token',
                'preview_hostname_verified_at',
                'preview_hostname_checked_at',
                'preview_hostname_check_error',
            ]);
        });
    }
};
