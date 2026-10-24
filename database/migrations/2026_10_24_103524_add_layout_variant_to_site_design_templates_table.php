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
        Schema::table('site_design_templates', function (Blueprint $table) {
            $table->string('layout_variant', 40)->default('standard')->after('style');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_design_templates', function (Blueprint $table) {
            $table->dropColumn('layout_variant');
        });
    }
};
