<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relationship managers are system users (any active staff login), not payroll
 * employees — repoints clients.relationship_manager_id from employees to users.
 * Existing values are Employee IDs and have no valid meaning against the new
 * target, so they're cleared; re-assign via Settings or the Member Summary
 * report afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('clients')->update(['relationship_manager_id' => null]);

        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['relationship_manager_id']);
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->foreign('relationship_manager_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('clients')->update(['relationship_manager_id' => null]);

        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['relationship_manager_id']);
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->foreign('relationship_manager_id')->references('id')->on('employees')->nullOnDelete();
        });
    }
};
