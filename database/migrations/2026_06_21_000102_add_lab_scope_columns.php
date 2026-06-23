<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tenant-owned tables that get a lab_id. */
    private array $tables = [
        'obligations', 'completions', 'signature_requests',
        'credentials', 'reminders_sent', 'audit_log',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('active');
        });

        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                // Nullable for the backfill step; populated by the BelongsToLab trait thereafter.
                $table->foreignId('lab_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('lab_id');
            });
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
