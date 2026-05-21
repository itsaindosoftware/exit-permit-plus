<?php

namespace App\Notifications;

use App\Models\ExitPermit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReimbursementSubmissionRequested extends Notification
{
    use Queueable;

    public function __construct(private readonly ExitPermit $exitPermit)
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

        return (new MailMessage())
            ->subject('Action Needed: Submit Reimbursement')
            ->greeting($greeting)
            ->line(sprintf('Exit Permit #%d has completed attendance verification and is on the reimbursement path.', $this->exitPermit->id))
            ->line('Please submit your reimbursement form as soon as possible.')
            ->action('Create Reimbursement', route('reimbursements.create', ['source' => 'exit_permit']));
    }
}
