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
        Schema::create('site_assistants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('public_key', 40)->unique();
            $table->boolean('enabled')->default(false);
            $table->string('name', 60);
            $table->string('avatar', 40)->default('maya');
            $table->string('voice', 20)->default('female');
            $table->string('greeting', 300)->nullable();
            $table->longText('knowledge')->nullable();
            $table->timestamp('knowledge_generated_at')->nullable();
            $table->unsignedInteger('messages_count')->default(0);
            $table->unsignedInteger('leads_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_assistants');
    }
};
