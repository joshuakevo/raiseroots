<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key'        => 'sms_subscription_amount',
            'value'      => '50000',
            'group'      => 'modules',
            'label'      => 'SMS Subscription Amount (UGX/month)',
            'type'       => 'number',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'sms_subscription_amount')->delete();
    }
};
