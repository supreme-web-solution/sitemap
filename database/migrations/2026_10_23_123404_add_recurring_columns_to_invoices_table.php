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
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('recurring_invoice_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
            $table->timestamp('emailed_at')->nullable()->after('sent_at');
            $table->unsignedTinyInteger('reminders_sent')->default(0)->after('emailed_at');
            $table->timestamp('last_reminded_at')->nullable()->after('reminders_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_invoice_id');
            $table->dropColumn(['emailed_at', 'reminders_sent', 'last_reminded_at']);
        });
    }
};
