<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_collateral_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed with the categories that were previously hardcoded in LoanCollateral::CATEGORIES,
        // so existing collateral records keep resolving to the same label.
        DB::table('loan_collateral_categories')->insert([
            ['key' => 'household', 'label' => 'Household', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'land_sale_agreement', 'label' => 'Land Sale Agreement', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'lc1_introduction_letter', 'label' => 'LC1 Introduction Letter', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'land_titles', 'label' => 'Land Titles', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'motor_vehicles', 'label' => 'Motor Vehicles', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'post_dated_cheques', 'label' => 'Post Dated Cheques', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_collateral_categories');
    }
};
