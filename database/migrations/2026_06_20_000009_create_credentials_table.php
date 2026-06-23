<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // C13 special case: per-credential expiry feeds the "Personnel licenses" row.
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obligation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('person_name', 120);
            $table->string('credential_type', 120);   // license, cert, CE
            $table->date('expiry_date');              // this is the "next_due" for C13 items
            $table->string('document_link', 500)->nullable();
            $table->timestamps();
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
