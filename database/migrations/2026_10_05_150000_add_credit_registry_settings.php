<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Connection to the shared ElTech Credit Registry (registry-service/): every ElTech
 * system pushes loan summaries there and looks clients up by national ID. Blank URL
 * or key = feature off.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            ['key' => 'crb_registry_url', 'value' => '', 'group' => 'crb', 'label' => 'Registry URL (e.g. https://registry.eltech-systems.com)', 'type' => 'text', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'crb_api_key',      'value' => '', 'group' => 'crb', 'label' => 'API Key (from the registry admin page)',                    'type' => 'text', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', ['crb_registry_url', 'crb_api_key'])->delete();
    }
};
