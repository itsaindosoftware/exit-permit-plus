<?php

namespace App\Notifications;

use App\Models\ExitPermit;
use App\Notifications\Channels\FirebasePushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExitPermitApprovalRequested extends Notification
{
    use Queueable;

    public function __construct(private readonly ExitPermit $exitPermit, private readonly string $stage)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', FirebasePushChannel::class];

        if (in_array($this->stage, ['manager', 'md', 'hr_manager'], true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipient = trim((string) ($notifiable->name ?? ''));
        $greeting = $recipient !== '' ? 'Hello ' . $recipient . ',' : 'Hello,';
        $subject = 'Exit Permit Approval Required';
        $message = 'An Exit Permit request requires your approval.';

        if ($this->stage === 'manager') {
            $subject = 'Exit Permit Awaiting Manager Approval';
            $message = sprintf('Exit Permit #%d from %s is awaiting manager approval.', $this->exitPermit->id, $this->exitPermit->user?->name ?? 'Employee');
        }

        if ($this->stage === 'md') {
            $subject = 'Exit Permit Awaiting MD Approval';
            $message = sprintf('Exit Permit #%d was approved by the manager and is awaiting MD approval.', $this->exitPermit->id);
        }

        if ($this->stage === 'hr_manager') {
            $subject = 'Exit Permit Awaiting HR Manager Approval';
            $message = sprintf('Exit Permit #%d was approved by the MD and is awaiting HR Manager approval.', $this->exitPermit->id);
        }

        if ($this->stage === 'attendance_verifier') {
            $subject = 'Exit Permit Check Required';
            $message = sprintf('Exit Permit #%d needs Sisca check before it is acknowledged.', $this->exitPermit->id);
        }

        return (new MailMessage())
            ->subject($subject)
            ->greeting($greeting)
            ->line($message)
            ->action('Open Approval List', route('exit-permit-approvals.index'));
    }

    public function toArray(object $notifiable): array
    {
        $title = 'Exit Permit Approval';
        $message = 'There is an Exit Permit request that requires action.';

        if ($this->stage === 'manager') {
            $title = 'Manager Approval Pending';
            $message = sprintf('Exit Permit #%d from %s is awaiting manager approval.', $this->exitPermit->id, $this->exitPermit->user?->name ?? 'Employee');
        }

        if ($this->stage === 'md') {
            $title = 'MD Approval Pending';
            $message = sprintf('Exit Permit #%d was approved by the manager and is awaiting MD approval.', $this->exitPermit->id);
        }

        if ($this->stage === 'hr_manager') {
            $title = 'HR Manager Approval Pending';
            $message = sprintf('Exit Permit #%d was approved by the MD and is awaiting HR Manager approval (HR PIC).', $this->exitPermit->id);
        }

        if ($this->stage === 'attendance_verifier') {
            $title = 'Check Exit Permit Pending (Sisca)';
            $message = sprintf('Exit Permit #%d needs Sisca check before it is acknowledged.', $this->exitPermit->id);
        }

        return [
            'type' => 'exit_permit_approval',
            'stage' => $this->stage,
            'title' => $title,
            'message' => $message,
            'exit_permit_id' => $this->exitPermit->id,
            'permit_date' => $this->exitPermit->permit_date ? (string) $this->exitPermit->permit_date : null,
        ];
    }

    public function toFcm(object $notifiable): array
    {
        $payload = $this->toArray($notifiable);

        return [
            'title' => (string) ($payload['title'] ?? 'Exit Permit Approval'),
            'body' => (string) ($payload['message'] ?? 'There is an Exit Permit request that requires action.'),
            'data' => [
                'type' => 'exit_permit_approval',
                'stage' => $this->stage,
                'exit_permit_id' => (string) $this->exitPermit->id,
                'target' => 'exit-permit-approvals',
            ],
        ];
    }
}
