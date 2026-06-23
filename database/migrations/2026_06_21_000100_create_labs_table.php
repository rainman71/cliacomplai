<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('clia_number', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('timezone', 64)->default('America/New_York');
            $table->string('drive_root_folder_id', 120)->nullable(); // per-lab Drive root
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labs');
    }
};
