<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A completed (or draft) official form: the answers captured by the wizard.
        // When tied to an obligation, submitting it creates a completion and advances the schedule.
        Schema::create('form_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->foreignId('obligation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('form_code', 20);              // e.g. CMP-173
            $table->string('title', 160);
            $table->json('answers');                      // captured field values
            $table->string('status', 20)->default('complete'); // draft | complete
            $table->date('completed_date')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('drive_file_id', 120)->nullable();
            $table->string('document_link', 500)->nullable();
            $table->timestamps();
            $table->index(['lab_id', 'form_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_responses');
    }
};
