<?php

namespace App\Notifications;

use App\Models\Reimbursement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReimbursementStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(private readonly Reimbursement $reimbursement, private readonly string $stage)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipient = trim((string) ($notifiable->name ?? ''));
        $greeting = $recipient !== '' ? 'Hello ' . $recipient . ',' : 'Hello,';
        $subject = 'Reimbursement Update';
        $message = 'Your reimbursement request has been updated.';

        if ($this->stage === 'manager_approved') {
            $subject = 'Reimbursement Approved by Manager';
            $message = sprintf('Reimbursement #%d has been approved by your manager and is waiting for MD approval.', $this->reimbursement->id);
        }

        if ($this->stage === 'md_approved') {
            $subject = 'Reimbursement Approved by MD';
            $message = sprintf('Reimbursement #%d has been approved by the MD and is waiting for submission to accounting.', $this->reimbursement->id);
        }

        if ($this->stage === 'submitted_to_accounting') {
            $subject = 'Reimbursement Submitted to Accounting';
            $message = sprintf('Reimbursement #%d has been submitted to accounting.', $this->reimbursement->id);
        }

        return (new MailMessage())
            ->subject($subject)
            ->greeting($greeting)
            ->line($message)
            ->action('View Reimbursement', route('reimbursements.edit', $this->reimbursement));
    }
}
