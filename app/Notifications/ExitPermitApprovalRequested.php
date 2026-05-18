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
        $message = 'Ada pengajuan Exit Permit yang membutuhkan tindakan.';

        if ($this->stage === 'manager') {
            $title = 'Menunggu Approval Manager';
            $message = sprintf('Exit Permit #%d dari %s menunggu approval Manager.', $this->exitPermit->id, $this->exitPermit->user?->name ?? 'Karyawan');
        }

        if ($this->stage === 'md') {
            $title = 'Menunggu Approval MD';
            $message = sprintf('Exit Permit #%d telah disetujui Manager dan menunggu approval MD.', $this->exitPermit->id);
        }

        if ($this->stage === 'hr_manager') {
            $title = 'Menunggu Approval HR Manager';
            $message = sprintf('Exit Permit #%d telah disetujui MD dan menunggu approval HR Manager (PIC HR).', $this->exitPermit->id);
        }

        if ($this->stage === 'attendance_verifier') {
            $title = 'Menunggu Verifikasi Absensi (Sisca)';
            $message = sprintf('Exit Permit #%d perlu verifikasi absensi oleh Sisca.', $this->exitPermit->id);
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
