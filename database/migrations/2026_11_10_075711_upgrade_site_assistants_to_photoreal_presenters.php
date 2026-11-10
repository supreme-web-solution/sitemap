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
        Schema::table('site_assistants', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'voice']);
            $table->string('presenter_id')->nullable()->after('name');
            $table->string('presenter_name')->nullable()->after('presenter_id');
            $table->string('presenter_gender', 20)->nullable()->after('presenter_name');
            $table->text('presenter_thumbnail_url')->nullable()->after('presenter_gender');
            $table->string('voice_id', 40)->default('nova')->after('presenter_thumbnail_url');
            $table->string('voice_name', 60)->nullable()->after('voice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_assistants', function (Blueprint $table) {
            $table->dropColumn([
                'presenter_id',
                'presenter_name',
                'presenter_gender',
                'presenter_thumbnail_url',
                'voice_id',
                'voice_name',
            ]);
            $table->string('avatar', 40)->default('maya')->after('name');
            $table->string('voice', 20)->default('female')->after('avatar');
        });
    }
};
