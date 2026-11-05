<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_center_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('source', 20)->default('web');
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['team_id', 'user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_center_messages');
    }
};
