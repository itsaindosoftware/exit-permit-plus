<?php

namespace App\Console\Commands;

use App\Models\AttendanceImportLog;
use App\Models\ExitPermit;
use App\Services\AttendanceMatchingService;
use Illuminate\Console\Command;

class ImportAttendanceFromSharingCommand extends Command
{
    protected $signature = 'attendance:import-sharing {--date=} {--dry-run}';

    protected $description = 'Auto import attendance harian dari folder sharing untuk exit permit company yang sudah approved MD';

    public function handle(AttendanceMatchingService $attendanceMatchingService): int
    {
        $targetDate = (string) ($this->option('date') ?: now()->toDateString());
        $dryRun = (bool) $this->option('dry-run');

        /** @var \Illuminate\Database\Eloquent\Collection<int, ExitPermit> $permits */
        $permits = ExitPermit::query()
            ->whereDate('permit_date', $targetDate)
            ->where('exit_type', ExitPermit::EXIT_TYPE_BUSINESS_TRIP)
            ->where('status', 'approved')
            ->whereNotNull('md_approved_at')
            ->whereNull('attendance_checked_at')
            ->with('requestors')
            ->get();

        if ($permits->isEmpty()) {
            $this->info('Tidak ada exit permit eligible untuk tanggal ' . $targetDate . '.');
            return self::SUCCESS;
        }

        $sourcePayload = $attendanceMatchingService->loadRowsWithSource();
        $attendanceRows = $sourcePayload['rows'];
        $source = $sourcePayload['source'];

        $applied = 0;

        foreach ($permits as $permit) {
            /** @var ExitPermit $permit */
            $preview = $attendanceMatchingService->buildPreview($permit, $attendanceRows, $source);

            $this->line(sprintf(
                'Permit #%d | requestor=%d | matched=%d | file=%s',
                $permit->id,
                (int) ($preview['summary']['total_requestors'] ?? 0),
                (int) ($preview['summary']['matched_count'] ?? 0),
                (string) ($source['file_name'] ?? '-'),
            ));

            if ($dryRun) {
                AttendanceImportLog::query()->create([
                    'exit_permit_id' => $permit->id,
                    'user_id' => null,
                    'source_disk' => $source['disk'] ?? null,
                    'source_path' => $source['path'] ?? null,
                    'source_file_name' => $source['file_name'] ?? null,
                    'import_type' => 'auto_dry_run',
                    'imported_at' => now(),
                    'total_requestors' => (int) ($preview['summary']['total_requestors'] ?? 0),
                    'matched_count' => (int) ($preview['summary']['matched_count'] ?? 0),
                    'has_valid_checkin' => (bool) ($preview['summary']['has_valid_checkin'] ?? false),
                ]);
                continue;
            }

            $matchedCount = $attendanceMatchingService->applyPreview($permit, $preview);

            $hasValidCheckin = (bool) ($preview['summary']['has_valid_checkin'] ?? false);

            $permit->attendance_checked_by = null;
            $permit->attendance_checked_at = now();
            $permit->has_valid_checkin = $hasValidCheckin;
            $permit->post_md_path = $hasValidCheckin && (bool) $permit->returned_to_office
                ? ExitPermit::POST_MD_PATH_MEAL
                : ExitPermit::POST_MD_PATH_REIMBURSEMENT;
            $permit->save();

            AttendanceImportLog::query()->create([
                'exit_permit_id' => $permit->id,
                'user_id' => null,
                'source_disk' => $source['disk'] ?? null,
                'source_path' => $source['path'] ?? null,
                'source_file_name' => $source['file_name'] ?? null,
                'import_type' => 'auto_applied',
                'imported_at' => now(),
                'total_requestors' => (int) ($preview['summary']['total_requestors'] ?? 0),
                'matched_count' => $matchedCount,
                'has_valid_checkin' => $hasValidCheckin,
            ]);

            $applied++;
        }

        $this->info(($dryRun ? 'Dry-run selesai. ' : 'Import selesai. ') . 'Total permit diproses: ' . $applied);

        return self::SUCCESS;
    }
}
