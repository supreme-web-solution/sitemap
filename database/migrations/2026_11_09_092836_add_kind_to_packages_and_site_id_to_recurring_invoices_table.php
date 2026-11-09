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
        Schema::table('packages', function (Blueprint $table) {
            $table->string('kind', 20)->default('service')->after('name');
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->foreignId('site_id')
                ->nullable()
                ->after('lead_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
