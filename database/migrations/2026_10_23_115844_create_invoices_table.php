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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);
            $table->string('status', 20)->default('draft')->index();
            $table->string('currency', 3)->default('usd');
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->date('due_on')->nullable();
            $table->text('notes')->nullable();
            $table->string('public_token', 40)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_via', 20)->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('pending_reference')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
