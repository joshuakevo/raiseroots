<?php

namespace App\Console\Commands;

use App\Services\SmsSubscriptionService;
use Illuminate\Console\Command;

class CheckSmsSubscriptions extends Command
{
    protected $signature = 'eltech:check-sms-subscriptions';

    protected $description = 'Poll MarzPay for any SMS subscription payment still pending (safety net alongside the webhook)';

    public function handle(SmsSubscriptionService $subscriptions)
    {
        $count = $subscriptions->checkAllPending();
        $this->info("Checked {$count} pending SMS subscription payment(s).");

        return self::SUCCESS;
    }
}
