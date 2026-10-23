<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'phone_e164')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('phone_e164', 20)->nullable()->index();
                $table->timestamp('messaging_consent_at')->nullable();
                $table->timestamp('messaging_opted_out_at')->nullable();
            });
        }

        DB::table('leads')->whereNotNull('phone')->orderBy('id')->chunkById(500, function ($leads) {
            foreach ($leads as $lead) {
                DB::table('leads')->where('id', $lead->id)->update(['phone_e164' => PhoneNumber::e164($lead->phone)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['phone_e164', 'messaging_consent_at', 'messaging_opted_out_at']);
        });
    }
};
