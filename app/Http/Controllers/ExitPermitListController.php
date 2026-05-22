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

        $submitter = trim((string) request()->query('submitter', ''));
        $requestor = trim((string) request()->query('requestor', ''));
        $permitDate = trim((string) request()->query('date', ''));
        $month = (int) request()->query('month', 0);
        $year = (int) request()->query('year', 0);
        $exitType = trim((string) request()->query('exit_type', ''));
        $destination = trim((string) request()->query('destination', ''));

        $query = ExitPermit::query()
            ->with([
                'user:id,name',
                'hrApprover:id,name',
                'requestors:id,exit_permit_id,row_number,name,employee_id,department,position',
            ])
            ->latest();

        if ($submitter !== '') {
            $query->whereHas('user', function ($subQuery) use ($submitter) {
                $subQuery->where('name', 'like', '%' . $submitter . '%');
            });
        }

        if ($requestor !== '') {
            $query->whereHas('requestors', function ($subQuery) use ($requestor) {
                $subQuery->where('name', 'like', '%' . $requestor . '%');
            });
        }

        if ($permitDate !== '') {
            $query->whereDate('permit_date', $permitDate);
        }

        if ($month >= 1 && $month <= 12) {
            $query->whereMonth('permit_date', $month);
        }

        if ($year >= 1900 && $year <= 3000) {
            $query->whereYear('permit_date', $year);
        }

        if ($exitType !== '' && in_array($exitType, ExitPermit::EXIT_TYPES, true)) {
            $query->where('exit_type', $exitType);
        }

        if ($destination !== '') {
            $query->where('destination', 'like', '%' . $destination . '%');
        }

        return Inertia::render('ExitPermitLists/Index', [
            'items' => $query
                ->paginate(15)
                ->withQueryString()
                ->through(fn(ExitPermit $exitPermit) => [
                    'id' => $exitPermit->id,
                    'submitter_name' => $exitPermit->user?->name,
                    'permit_date' => $this->toDateOnly($exitPermit->permit_date),
                    'start_time' => $this->toHourMinute($exitPermit->start_time),
                    'end_time' => $this->toHourMinute($exitPermit->end_time),
                    'destination' => $exitPermit->destination,
                    'exit_type' => $exitPermit->exit_type,
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
            'filters' => [
                'submitter' => $submitter,
                'requestor' => $requestor,
                'date' => $permitDate,
                'month' => $month >= 1 && $month <= 12 ? (string) $month : '',
                'year' => $year >= 1900 && $year <= 3000 ? (string) $year : '',
                'exit_type' => $exitType,
                'destination' => $destination,
            ],
            'exitTypes' => ExitPermit::EXIT_TYPES,
        ]);
    }

    private function authorizeHrOnly(): void
    {
        abort_unless(in_array(request()->user()?->role?->code, ['hr', 'admin'], true), 403);
    }

    private function approvalStageLabel(ExitPermit $exitPermit): string
    {
        $approverName = $exitPermit->hrApprover?->name ?? 'HR Approver';

        if ($exitPermit->status === 'approved') {
            if ($exitPermit->exit_type !== ExitPermit::EXIT_TYPE_BUSINESS_TRIP) {
                return 'Approved by HR Manager | Completed (Acknowledged by Sisca)';
            }

            if (!$exitPermit->attendance_checked_at) {
                return 'Approved by HR Manager | Waiting for Sisca attendance verification';
            }

            if ($exitPermit->post_md_path === ExitPermit::POST_MD_PATH_MEAL) {
                return 'Approved by HR Manager | Acknowledged by Sisca (HRD)';
            }

            if ($exitPermit->post_md_path === ExitPermit::POST_MD_PATH_REIMBURSEMENT) {
                return 'Approved by HR Manager | Reimbursement Path';
            }

            return 'Approved by HR Manager | Acknowledged by HR Manager (' . $approverName . ')';
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
            return 'Acknowledged by Sisca (HRD)';
        }

        if (
            $exitPermit->status === 'approved'
            && (bool) $exitPermit->attendance_checked_at
            && (bool) $exitPermit->has_valid_checkin
        ) {
            return 'Acknowledged by Sisca (HRD)';
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
