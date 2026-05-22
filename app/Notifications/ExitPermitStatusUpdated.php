<?php

namespace App\Notifications;

use App\Models\ExitPermit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExitPermitStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(private readonly ExitPermit $exitPermit, private readonly string $stage)
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
        $subject = 'Exit Permit Update';
        $message = 'Your Exit Permit has been updated.';

        if ($this->stage === 'manager_approved') {
            $subject = 'Exit Permit Approved by Manager';
            $message = sprintf('Exit Permit #%d has been approved by your manager and is waiting for MD approval.', $this->exitPermit->id);
        }

        if ($this->stage === 'md_approved') {
            $subject = 'Exit Permit Approved by MD';
            $message = sprintf('Exit Permit #%d has been approved by the MD and is waiting for HR Manager approval.', $this->exitPermit->id);
        }

        if ($this->stage === 'hr_manager_approved') {
            $subject = 'Exit Permit Approved by HR Manager';
            $message = sprintf('Exit Permit #%d has been approved by the HR Manager and is waiting for attendance verification.', $this->exitPermit->id);
        }

        if ($this->stage === 'completed_meal') {
            $subject = 'Exit Permit Completed (Meal)';
            $message = sprintf('Exit Permit #%d has completed attendance verification and is on the meal path.', $this->exitPermit->id);
        }

        return (new MailMessage())
            ->subject($subject)
            ->greeting($greeting)
            ->line($message)
            ->line('Exit Permit ID: #' . $this->exitPermit->id)
            ->action('View Exit Permit', route('exit-permits.show', $this->exitPermit));
    }
}
