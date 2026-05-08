<?php

namespace App\Http\Controllers;

use App\Models\ExitPermit;
use Inertia\Inertia;
use Inertia\Response;

class ExitPermitListController extends Controller
{
    public function __invoke(): Response
    {
        $this->authorizeHrOnly();

        $query = ExitPermit::query()
            ->with([
                'user:id,name',
                'hrApprover:id,name',
                'requestors:id,exit_permit_id,row_number,name,employee_id,department,position',
            ])
            ->latest();

        return Inertia::render('ExitPermitLists/Index', [
            'items' => $query
                ->paginate(15)
                ->through(fn(ExitPermit $exitPermit) => [
                    'id' => $exitPermit->id,
                    'submitter_name' => $exitPermit->user?->name,
                    'permit_date' => $this->toDateOnly($exitPermit->permit_date),
                    'start_time' => $this->toHourMinute($exitPermit->start_time),
                    'end_time' => $this->toHourMinute($exitPermit->end_time),
                    'destination' => $exitPermit->destination,
                    'requestors' => $exitPermit->requestors
                        ->map(fn($requestor) => [
                            'name' => $requestor->name,
                            'employee_id' => $requestor->employee_id,
                            'department' => $requestor->department,
                            'position' => $requestor->position,
                        ])
                        ->values()
                        ->all(),
                    'status' => $exitPermit->status,
                    'status_label' => $this->statusLabel($exitPermit),
                    'approval_stage' => $this->approvalStageLabel($exitPermit),
                ]),
        ]);
    }

    private function authorizeHrOnly(): void
    {
        abort_unless(request()->user()?->role?->code === 'hr', 403);
    }

    private function approvalStageLabel(ExitPermit $exitPermit): string
    {
        $approverName = $exitPermit->hrApprover?->name ?? 'HR Approver';

        if ($exitPermit->status === 'approved') {
            if ($exitPermit->exit_type !== ExitPermit::EXIT_TYPE_BUSINESS_TRIP) {
                return 'Approved by HR Manager | Selesai (Diketahui Sisca)';
            }

            if (!$exitPermit->attendance_checked_at) {
                return 'Approved by HR Manager | Menunggu verifikasi absensi Sisca';
            }

            if ($exitPermit->post_md_path === ExitPermit::POST_MD_PATH_MEAL) {
                return 'Approved by HR Manager | Jalur Meal';
            }

            if ($exitPermit->post_md_path === ExitPermit::POST_MD_PATH_REIMBURSEMENT) {
                return 'Approved by HR Manager | Jalur Reimbursement';
            }

            return 'Approved by HR Manager | Sepengetahuan HR Manager (' . $approverName . ')';
        }

        if ($exitPermit->status === 'rejected') {
            if ($exitPermit->hr_verified_at) {
                return 'Rejected by HR Manager';
            }

            return $exitPermit->md_approved_at ? 'Rejected by MD' : 'Rejected by Manager';
        }

        if (!$exitPermit->manager_approved_at) {
            return 'Waiting Manager Approval';
        }

        if (!$exitPermit->md_approved_at) {
            return 'Waiting MD Approval | PIC HR: ' . $approverName;
        }

        if (!$exitPermit->hr_verified_at) {
            return 'Waiting HR Manager Approval | PIC HR: ' . $approverName;
        }

        return 'Pending';
    }

    private function statusLabel(ExitPermit $exitPermit): string
    {
        if (
            $exitPermit->status === 'approved'
            && $exitPermit->exit_type !== ExitPermit::EXIT_TYPE_BUSINESS_TRIP
            && (bool) $exitPermit->hr_verified_at
        ) {
            return 'Diketahui Sisca (HRD)';
        }

        if (
            $exitPermit->status === 'approved'
            && (bool) $exitPermit->attendance_checked_at
            && (bool) $exitPermit->has_valid_checkin
        ) {
            return 'Checked By HR: Sisca';
        }

        return strtoupper((string) $exitPermit->status);
    }

    private function toDateOnly($value): ?string
    {
        if (!$value) {
            return null;
        }

        return substr((string) $value, 0, 10);
    }

    private function toHourMinute(?string $time): ?string
    {
        if (!$time) {
            return null;
        }

        return substr($time, 0, 5);
    }
}
