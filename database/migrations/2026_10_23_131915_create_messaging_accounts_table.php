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
        Schema::create('messaging_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('api_key');
            $table->text('webhook_secret');
            $table->string('webhook_key', 40)->unique();
            $table->string('sms_from', 20)->nullable();
            $table->string('whatsapp_account_id', 60)->nullable();
            $table->string('timezone', 60)->default('America/New_York');
            $table->unsignedTinyInteger('quiet_start_hour')->default(21);
            $table->unsignedTinyInteger('quiet_end_hour')->default(8);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messaging_accounts');
    }
};