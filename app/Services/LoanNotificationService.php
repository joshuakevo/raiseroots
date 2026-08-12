<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use Carbon\Carbon;

class LoanNotificationService
{
    public function __construct(protected SmsService $sms) {}

    public function loanDisbursed(Loan $loan): void
    {
        $client = $loan->client;
        if (!$client?->phone) return;

        $firstDue = $loan->schedules()->orderBy('installment_no')->first();

        $message = sprintf(
            "Dear %s, your loan %s of UGX %s has been disbursed. First payment of UGX %s is due on %s. - ElTech",
            $client->name,
            $loan->loan_number,
            number_format($loan->principal, 0),
            $firstDue ? number_format($firstDue->total_due, 0) : '0',
            $firstDue ? $firstDue->due_date->format('d M Y') : 'n/a'
        );

        $this->sms->send($client->phone, $message, [
            'client_id'       => $client->id,
            'client_name'     => $client->name,
            'recipient_group' => 'loan_disbursed',
        ]);
    }

    public function repaymentReceived(Loan $loan, LoanRepayment $repayment): void
    {
        $client = $loan->client;
        if (!$client?->phone) return;

        $message = sprintf(
            "Dear %s, we received your payment of UGX %s for loan %s on %s. Outstanding balance: UGX %s. Thank you. - ElTech",
            $client->name,
            number_format($repayment->amount, 0),
            $loan->loan_number,
            Carbon::parse($repayment->payment_date)->format('d M Y'),
            number_format($loan->total_outstanding, 0)
        );

        $this->sms->send($client->phone, $message, [
            'client_id'       => $client->id,
            'client_name'     => $client->name,
            'recipient_group' => 'repayment_received',
        ]);
    }

    public function loanClosed(Loan $loan): void
    {
        $client = $loan->client;
        if (!$client?->phone) return;

        $message = sprintf(
            "Dear %s, congratulations! Loan %s has been fully paid off and is now closed. Thank you for banking with us. - ElTech",
            $client->name,
            $loan->loan_number
        );

        $this->sms->send($client->phone, $message, [
            'client_id'       => $client->id,
            'client_name'     => $client->name,
            'recipient_group' => 'loan_closed',
        ]);
    }

    public function upcomingPaymentReminder(Loan $loan, LoanSchedule $schedule): void
    {
        $client = $loan->client;
        if (!$client?->phone) return;

        $due = ($schedule->principal_due - $schedule->principal_paid) + ($schedule->interest_due - $schedule->interest_paid);

        $message = sprintf(
            "Dear %s, a payment of UGX %s for loan %s is due on %s. Please pay on time to avoid penalties. - ElTech",
            $client->name,
            number_format($due, 0),
            $loan->loan_number,
            $schedule->due_date->format('d M Y')
        );

        $this->sms->send($client->phone, $message, [
            'client_id'       => $client->id,
            'client_name'     => $client->name,
            'recipient_group' => 'payment_reminder',
        ]);
    }

    public function overdueDefaultNotice(Loan $loan, float $overdueAmount, int $daysOverdue, float $penalty): void
    {
        $client = $loan->client;
        if (!$client?->phone) return;

        $message = sprintf(
            "Dear %s, loan %s has an overdue balance of UGX %s (%d day%s overdue, UGX %s penalty accrued). Please pay urgently to avoid further penalties. - ElTech",
            $client->name,
            $loan->loan_number,
            number_format($overdueAmount, 0),
            $daysOverdue,
            $daysOverdue === 1 ? '' : 's',
            number_format($penalty, 0)
        );

        $this->sms->send($client->phone, $message, [
            'client_id'       => $client->id,
            'client_name'     => $client->name,
            'recipient_group' => 'overdue_notice',
        ]);
    }
}
