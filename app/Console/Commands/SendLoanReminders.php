<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Services\LoanNotificationService;
use App\Services\LoanService;
use Illuminate\Console\Command;

class SendLoanReminders extends Command
{
    protected $signature = 'eltech:send-loan-reminders';

    protected $description = 'SMS reminders for installments due in 3 days, and daily notices for overdue/defaulting loans';

    public function handle(LoanNotificationService $notifier, LoanService $loanService)
    {
        $this->sendUpcomingReminders($notifier);
        $this->sendOverdueNotices($notifier, $loanService);

        return self::SUCCESS;
    }

    protected function sendUpcomingReminders(LoanNotificationService $notifier): void
    {
        $reminderDate = now()->addDays(3)->toDateString();

        $schedules = LoanSchedule::whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', $reminderDate)
            ->whereHas('loan', fn ($q) => $q->where('status', 'active'))
            ->with('loan.client')
            ->get();

        foreach ($schedules as $schedule) {
            $notifier->upcomingPaymentReminder($schedule->loan, $schedule);
        }

        $this->info("Upcoming payment reminders sent: {$schedules->count()}");
    }

    protected function sendOverdueNotices(LoanNotificationService $notifier, LoanService $loanService): void
    {
        $loans = Loan::whereIn('status', ['active', 'defaulted'])
            ->whereHas('schedules', fn ($q) => $q->where('due_date', '<', now()->toDateString())->where('status', '!=', 'paid'))
            ->with('client', 'product')
            ->get();

        $sent = 0;
        foreach ($loans as $loan) {
            $overdueSchedules = $loan->schedules()
                ->where('due_date', '<', now()->toDateString())
                ->where('status', '!=', 'paid')
                ->get();

            if ($overdueSchedules->isEmpty()) continue;

            $overdueAmount = $overdueSchedules->sum(
                fn ($s) => ($s->principal_due - $s->principal_paid) + ($s->interest_due - $s->interest_paid)
            );
            $daysOverdue = now()->diffInDays($overdueSchedules->min('due_date'));
            $penalty     = $loanService->calculatePenaltyPublic($loan);

            $notifier->overdueDefaultNotice($loan, $overdueAmount, $daysOverdue, $penalty);
            $sent++;
        }

        $this->info("Overdue/default notices sent: {$sent}");
    }
}
