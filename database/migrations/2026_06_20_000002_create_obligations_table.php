<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();              // C01..C13
            $table->string('category', 80);
            $table->string('name', 200);
            $table->string('frequency_label', 120);           // "3 events / year"
            $table->unsignedSmallInteger('interval_months')->nullable(); // null for C13
            $table->string('owner_role', 80);
            $table->date('last_completed')->nullable();       // = MAX(completions.completed_date)
            $table->date('next_due')->nullable();             // cached: last_completed + interval
            $table->string('signature_status', 30)->default('not_started');
            $table->string('document_link', 500)->nullable(); // Drive file/folder URL
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligations');
    }
};
