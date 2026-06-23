<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only audit trail (retain >= 7 years for HIPAA/compliance).
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 40);   // 'obligation','signature_request',...
            $table->unsignedBigInteger('entity_id');
            $table->string('field', 60)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('action', 40);        // 'update','status_change','link_added'
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
            $table->index('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
