<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Membership = a user's access to a lab (with a per-lab active flag).
        Schema::create('lab_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['lab_id', 'user_id']);
        });

        // A user can hold MULTIPLE roles at one lab (LD + Tech Supervisor + ...).
        Schema::create('lab_user_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_user_id')->constrained('lab_user')->cascadeOnDelete();
            $table->string('role', 40);
            $table->unique(['lab_user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_user_role');
        Schema::dropIfExists('lab_user');
    }
};
