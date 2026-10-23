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
        Schema::create('site_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('hostname', 253)->index();
            $table->string('verification_token', 40);
            $table->timestamp('verified_at')->nullable();
            // Set only while verified, so a hostname can be live on one site at a time
            // while several people may still be trying to prove they own it.
            $table->string('verified_hostname', 253)->nullable()->unique();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('check_error')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'hostname']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_domains');
    }
};
