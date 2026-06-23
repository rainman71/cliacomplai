<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // History: one row each time an obligation is completed (audit-ready).
        Schema::create('completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obligation_id')->constrained()->cascadeOnDelete();
            $table->date('completed_date');
            $table->string('document_link', 500)->nullable(); // evidence for THIS completion
            $table->string('drive_file_id', 120)->nullable(); // signed PDF in Drive
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['obligation_id', 'completed_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('completions');
    }
};
