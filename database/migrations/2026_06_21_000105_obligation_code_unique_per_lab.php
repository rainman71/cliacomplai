<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Obligation codes (C01..C13) repeat across labs, so the unique constraint must be
 * per-lab, not global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obligations', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['lab_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('obligations', function (Blueprint $table) {
            $table->dropUnique(['lab_id', 'code']);
            $table->unique(['code']);
        });
    }
};
