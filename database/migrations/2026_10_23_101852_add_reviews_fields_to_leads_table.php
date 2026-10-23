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
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('reviews_fetched_at')->nullable();
            $table->string('reviews_fetch_status')->default('idle');
            $table->string('reviews_fetch_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['reviews_fetched_at', 'reviews_fetch_status', 'reviews_fetch_error']);
        });
    }
};
