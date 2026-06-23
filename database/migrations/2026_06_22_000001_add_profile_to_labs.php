<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flexible profile bag (director, supervisors, hours, specialties, etc.) used to
        // auto-fill forms. Kept as JSON so new profile fields don't need a migration each.
        Schema::table('labs', function (Blueprint $table) {
            $table->json('profile')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('labs', function (Blueprint $table) {
            $table->dropColumn('profile');
        });
    }
};
