<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A signature run for one completion.
        Schema::create('signature_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obligation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('completion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40)->default('google_esignature');
            $table->string('envelope_id', 120)->nullable();   // external ref (DocuSign etc.)
            $table->date('sent_date')->nullable();
            $table->date('deadline')->nullable();             // = obligation.next_due at send time
            $table->string('status', 30)->default('out_for_signature');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_requests');
    }
};
