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
        Schema::create('sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('follow_up_sequence_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedTinyInteger('next_position')->default(0);
            $table->timestamp('next_step_at')->nullable();
            $table->string('stopped_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_step_at']);
            $table->unique(['lead_id', 'follow_up_sequence_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequence_enrollments');
    }
};
