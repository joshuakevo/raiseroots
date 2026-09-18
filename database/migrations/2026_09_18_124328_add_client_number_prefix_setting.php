<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $now = now();

        foreach ([
            ['key' => 'client_number_prefix',  'value' => 'SIP/', 'group' => 'general', 'label' => 'Client Number Prefix', 'type' => 'text'],
            ['key' => 'client_number_padding', 'value' => '3',    'group' => 'general', 'label' => 'Client Number Padding (digits)', 'type' => 'number'],
        ] as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down()
    {
        DB::table('system_settings')->whereIn('key', ['client_number_prefix', 'client_number_padding'])->delete();
    }
};
