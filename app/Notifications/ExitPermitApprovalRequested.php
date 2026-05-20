<?php

namespace App\Notifications;

use App\Models\ExitPermit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExitPermitApprovalRequested extends Notification
{
    use Queueable;

    public function __construct(private readonly ExitPermit $exitPermit, private readonly string $stage)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
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
            $title = 'Attendance Verification Pending (Sisca)';
            $message = sprintf('Exit Permit #%d needs attendance verification by Sisca.', $this->exitPermit->id);
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
}
