<?php

namespace App\Notifications;

use App\Models\Reimbursement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReimbursementApprovalRequested extends Notification
{
    use Queueable;

    public function __construct(private readonly Reimbursement $reimbursement, private readonly string $stage)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipient = trim((string) ($notifiable->name ?? ''));
        $greeting = $recipient !== '' ? 'Hello ' . $recipient . ',' : 'Hello,';
        $subject = 'Reimbursement Approval Required';
        $message = 'A reimbursement request requires your approval.';

        if ($this->stage === 'manager') {
            $subject = 'Reimbursement Awaiting Manager Approval';
            $message = sprintf('Reimbursement #%d from %s is awaiting manager approval.', $this->reimbursement->id, $this->reimbursement->user?->name ?? 'Employee');
        }

        if ($this->stage === 'md') {
            $subject = 'Reimbursement Awaiting MD Approval';
            $message = sprintf('Reimbursement #%d was approved by the manager and is awaiting MD approval.', $this->reimbursement->id);
        }

        return (new MailMessage())
            ->subject($subject)
            ->greeting($greeting)
            ->line($message)
            ->action('Open Approval List', route('reimbursement-approvals.index'));
    }

    public function toArray(object $notifiable): array
    {
        $title = 'Reimbursement Approval';
        $message = 'A reimbursement request requires action.';

        if ($this->stage === 'manager') {
            $title = 'Manager Approval Pending';
            $message = sprintf('Reimbursement #%d from %s is awaiting manager approval.', $this->reimbursement->id, $this->reimbursement->user?->name ?? 'Employee');
        }

        if ($this->stage === 'md') {
            $title = 'MD Approval Pending';
            $message = sprintf('Reimbursement #%d was approved by the manager and is awaiting MD approval.', $this->reimbursement->id);
        }

        return [
            'type' => 'reimbursement_approval',
            'stage' => $this->stage,
            'title' => $title,
            'message' => $message,
            'reimbursement_id' => $this->reimbursement->id,
            'request_date' => $this->reimbursement->request_date ? (string) $this->reimbursement->request_date : null,
        ];
    }
}
