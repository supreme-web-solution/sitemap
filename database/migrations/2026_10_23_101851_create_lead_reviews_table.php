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
        Schema::create('lead_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->string('author_name');
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->string('posted_at_text')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['lead_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_reviews');
    }
};
