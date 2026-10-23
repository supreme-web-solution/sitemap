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
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('interval', 20);
            $table->date('starts_on');
            $table->date('next_run_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('due_in_days')->default(14);
            $table->boolean('email_client')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->json('items');
            $table->unsignedInteger('cycles_run')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_run_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices');
    }
};
