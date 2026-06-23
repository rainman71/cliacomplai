<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // RBAC role + the Google OAuth subject id (SSO, no local password store).
            $table->string('role', 40)->default('tech_staff')->after('email');
            $table->string('google_sub', 190)->nullable()->unique()->after('role');
            $table->boolean('active')->default(true)->after('google_sub');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'google_sub', 'active']);
        });
    }
};
