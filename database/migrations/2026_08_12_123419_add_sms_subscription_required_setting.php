<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key'        => 'sms_subscription_required',
            'value'      => '1',
            'group'      => 'modules',
            'label'      => 'Require SMS Subscription',
            'type'       => 'boolean',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'sms_subscription_required')->delete();
    }
};
