<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab management companies — the tenant tier ABOVE labs. A company owns many labs and (from
 * Phase 2) its own curated P&P/obligation template that seeds each lab beneath it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 64)->nullable()->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_companies');
    }
};
