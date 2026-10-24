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
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_payment_method_id')->nullable();
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->string('collection_mode', 20)->default('invoice');
            $table->string('payment_provider', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'stripe_payment_method_id']);
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropColumn(['collection_mode', 'payment_provider']);
        });
    }
};
