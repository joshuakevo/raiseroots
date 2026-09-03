<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Post savings interest at midnight on the 1st of every month
        $schedule->command('eltech:post-interest')->monthlyOn(1, '00:00');

        // Recompute denormalized fields (loan default status, savings balances, etc.)
        // from source of truth every night
        $schedule->command('eltech:reconcile')->dailyAt('00:30');

        // SMS reminders for upcoming due installments and overdue/defaulting loans
        $schedule->command('eltech:send-loan-reminders')->dailyAt('08:00');

        // Safety-net poll for pending SMS subscription payments, alongside the MarzPay webhook
        $schedule->command('eltech:check-sms-subscriptions')->everyTenMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
