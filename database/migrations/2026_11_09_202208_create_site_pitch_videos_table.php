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
        Schema::create('site_pitch_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('public_token', 40)->unique();
            $table->string('status')->default('draft');
            $table->text('script')->nullable();
            $table->string('presenter_id')->nullable();
            $table->string('presenter_name')->nullable();
            $table->string('presenter_gender', 20)->nullable();
            $table->text('presenter_thumbnail_url')->nullable();
            $table->string('voice_id')->nullable();
            $table->string('voice_name')->nullable();
            $table->string('voice_provider', 40)->nullable();
            $table->string('did_clip_id')->nullable();
            $table->string('video_path')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('generation_started_at')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_pitch_videos');
    }
};
