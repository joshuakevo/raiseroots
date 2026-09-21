<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_loan_officer')->default(false)->after('is_active');
        });

        // Anyone already assigned as a client's Loan Officer keeps appearing in the
        // dropdowns; everyone else is opt-in from Users > Edit.
        DB::table('users')
            ->whereIn('id', DB::table('clients')->whereNotNull('relationship_manager_id')->select('relationship_manager_id'))
            ->update(['is_loan_officer' => true]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_loan_officer');
        });
    }
};
