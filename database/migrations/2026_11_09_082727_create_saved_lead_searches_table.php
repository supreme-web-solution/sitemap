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
        Schema::create('saved_lead_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('keyword', 100);
            $table->string('location', 150);
            $table->string('website_filter');
            $table->decimal('minimum_rating', 2, 1)->nullable();
            $table->unsignedTinyInteger('max_results')->default(20);
            $table->timestamps();

            $table->unique(['team_id', 'name']);
            $table->index(['team_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_lead_searches');
    }
};
