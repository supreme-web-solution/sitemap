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
        Schema::create('mail_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('email_address');
            $table->text('credentials')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_error')->nullable();
            $table->string('sync_cursor')->nullable();
            $table->timestamps();
        });

        Schema::create('email_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('subject_normalized');
            $table->string('root_message_id')->nullable();
            $table->string('last_outbound_message_id')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'lead_id']);
            $table->index(['team_id', 'subject_normalized']);
        });

        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_thread_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_activity_id')->nullable()->constrained('lead_activities')->nullOnDelete();
            $table->string('external_message_id');
            $table->string('thread_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->string('from_address');
            $table->string('to_address');
            $table->string('subject');
            $table->string('snippet', 500);
            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(['mail_connection_id', 'external_message_id']);
            $table->index(['team_id', 'received_at']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('last_email_reply_at')->nullable()->after('last_contacted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('last_email_reply_at');
        });

        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('email_threads');
        Schema::dropIfExists('mail_connections');
    }
};
