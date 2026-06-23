<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotency for the reminder cron (don't double-send).
        Schema::create('reminders_sent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obligation_id')->constrained()->cascadeOnDelete();
            // due_30|due_7|due_0|overdue_1|overdue_weekly
            $table->string('reminder_type', 20);
            $table->date('due_date');            // the due date this reminder was for
            $table->timestamp('sent_at')->useCurrent();
            $table->unique(['obligation_id', 'reminder_type', 'due_date'], 'uniq_reminder');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders_sent');
    }
};
