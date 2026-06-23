<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-signer status within a request -> powers "1 of 3 signed".
        Schema::create('signature_request_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signature_request_id')->constrained()->cascadeOnDelete();
            $table->string('signer_name', 120);
            $table->string('signer_email', 190)->nullable();
            $table->string('status', 20)->default('not_sent'); // not_sent|pending|signed|rejected
            $table->dateTime('signed_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_request_signers');
    }
};
