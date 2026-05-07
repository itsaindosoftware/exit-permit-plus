<?php

namespace App\Services;

use App\Models\ExitPermit;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceMatchingService
{
    /**
     * @return array{rows: Collection<int, array<string, mixed>>, source: array<string, mixed>}
     */
    public function loadRowsWithSource(?UploadedFile $uploadedFile = null): array
    {
        if ($uploadedFile instanceof UploadedFile) {
            $rows = $this->parseAttendanceFile(
                (string) $uploadedFile->getRealPath(),
                strtolower((string) $uploadedFile->getClientOriginalExtension()),
            );

            return [
                'rows' => $rows,
                'source' => [
                    'disk' => 'upload',
                    'path' => null,
                    'file_name' => $uploadedFile->getClientOriginalName(),
                    'loaded_at' => now()->toDateTimeString(),
                ],
            ];
        }

        $disk = (string) config('attendance.source_disk', 'local');
        $path = trim((string) config('attendance.source_path', ''), '/');

        if ($path === '') {
            throw ValidationException::withMessages([
                'attendance_file' => 'File attendance wajib diupload atau set ATTENDANCE_SOURCE_PATH terlebih dahulu.',
            ]);
        }

        $diskInstance = Storage::disk($disk);
        $files = collect($diskInstance->files($path))
            ->filter(function (string $filePath) {
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                return in_array($extension, ['csv', 'txt', 'xlsx'], true);
            })
            ->sortByDesc(fn(string $filePath) => $diskInstance->lastModified($filePath))
            ->values();

        $latestFile = $files->first();

        if (!$latestFile) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Tidak ada file attendance (csv/xlsx) di folder sharing.',
            ]);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'attendance_');

        if ($tempPath === false) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Gagal menyiapkan file sementara attendance.',
            ]);
        }

        file_put_contents($tempPath, (string) $diskInstance->get($latestFile));

        try {
            $extension = strtolower((string) pathinfo($latestFile, PATHINFO_EXTENSION));
            $rows = $this->parseAttendanceFile($tempPath, $extension);

            return [
                'rows' => $rows,
                'source' => [
                    'disk' => $disk,
                    'path' => $latestFile,
                    'file_name' => basename($latestFile),
                    'loaded_at' => now()->toDateTimeString(),
                ],
            ];
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * @param Collection<int, array<string, mixed>> $attendanceRows
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function buildPreview(ExitPermit $exitPermit, Collection $attendanceRows, array $source): array
    {
        $permitDate = $this->toDateOnly($exitPermit->permit_date);

        $allowedDepartments = collect(config('attendance.company_departments', ['BIPO', 'INTERNSHIP', 'OUTSOURCE']))
            ->map(fn($department) => strtoupper(trim((string) $department)))
            ->filter()
            ->values()
            ->all();

        $attendanceForDate = $attendanceRows
            ->where('attendance_date', $permitDate)
            ->values();

        if ($attendanceForDate->isEmpty()) {
            // Fallback to all rows so preview can still match by NIK or Name.
            $attendanceForDate = $attendanceRows;
        }

        $attendanceEmployeeIds = $attendanceForDate
            ->pluck('employee_id')
            ->filter()
            ->unique();

        $attendanceNames = $attendanceForDate
            ->pluck('name')
            ->filter()
            ->unique();

        $requestorRows = $exitPermit->requestors()
            ->orderBy('row_number')
            ->get();

        $matchedCount = 0;

        $items = $requestorRows
            ->map(function ($requestor) use ($attendanceEmployeeIds, $attendanceNames, $allowedDepartments, &$matchedCount) {
                $department = strtoupper(trim((string) ($requestor->department ?? '')));
                $isCompanyDepartment = in_array($department, $allowedDepartments, true);

                $normalizedEmployeeId = $this->normalizeEmployeeId($requestor->employee_id);
                $normalizedName = $this->normalizePersonName($requestor->name);

                $matchByEmployeeId = $normalizedEmployeeId && $attendanceEmployeeIds->contains($normalizedEmployeeId);
                $matchByName = !$matchByEmployeeId && $normalizedName && $attendanceNames->contains($normalizedName);

                // Temporary: disable department scope requirement.
                // Keep $isCompanyDepartment for UI diagnostics (company_scope field).
                // $matched = $isCompanyDepartment && ($matchByEmployeeId || $matchByName);
                $matched = $matchByEmployeeId || $matchByName;

                if ($matched) {
                    $matchedCount++;
                }

                return [
                    'id' => $requestor->id,
                    'row_number' => $requestor->row_number,
                    'name' => $requestor->name,
                    'employee_id' => $requestor->employee_id,
                    'position' => $requestor->position,
                    'department' => $requestor->department,
                    'before_reimburs_lunch_box' => $requestor->reimburs_lunch_box,
                    'matched' => $matched,
                    'matched_by' => $matchByEmployeeId ? 'employee_id' : ($matchByName ? 'name' : null),
                    'match_by_employee_id' => $matchByEmployeeId,
                    'match_by_name' => $matchByName,
                    'recommended_reimburs_lunch_box' => $matched ? 'Y' : 'N',
                    'company_scope' => $isCompanyDepartment,
                ];
            })
            ->values()
            ->all();

        return [
            'exit_permit_id' => $exitPermit->id,
            'permit_date' => $permitDate,
            'source' => $source,
            'summary' => [
                'total_requestors' => count($items),
                'matched_count' => $matchedCount,
                'attendance_rows_for_date' => $attendanceForDate->count(),
                'has_valid_checkin' => $matchedCount > 0,
            ],
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $preview
     */
    public function applyPreview(ExitPermit $exitPermit, array $preview): int
    {
        $items = collect($preview['items'] ?? []);
        $matchedCount = 0;

        $requestors = $exitPermit->requestors()->get()->keyBy('id');

        foreach ($items as $item) {
            $requestor = $requestors->get((int) ($item['id'] ?? 0));

            if (!$requestor) {
                continue;
            }

            $newValue = strtoupper((string) ($item['recommended_reimburs_lunch_box'] ?? 'N'));
            $requestor->reimburs_lunch_box = in_array($newValue, ['Y', 'N'], true) ? $newValue : 'N';
            $requestor->save();

            if ((bool) ($item['matched'] ?? false)) {
                $matchedCount++;
            }
        }

        return $matchedCount;
    }

    private function parseAttendanceFile(string $filePath, string $extension): Collection
    {
        $rawRows = match ($extension) {
            'csv', 'txt', 'xlsx' => $this->readSpreadsheetRows($filePath, $extension),
            default => throw ValidationException::withMessages([
                'attendance_file' => 'Format file attendance belum didukung. Gunakan CSV atau XLSX.',
            ]),
        };

        if (count($rawRows) < 2) {
            throw ValidationException::withMessages([
                'attendance_file' => 'File attendance kosong atau tidak memiliki header + data.',
            ]);
        }

        $headers = array_map(fn($value) => $this->normalizeHeader((string) $value), $rawRows[0]);

        return collect(array_slice($rawRows, 1))
            ->map(function (array $row) use ($headers) {
                $assoc = [];

                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $assoc[$header] = trim((string) ($row[$index] ?? ''));
                }

                return $assoc;
            })
            ->filter(fn(array $row) => count(array_filter($row, fn($value) => $value !== '')) > 0)
            ->reject(fn(array $row) => $this->isRepeatAttendanceRow($row))
            ->map(function (array $row) {
                $employeeId = $this->firstFilled($row, [
                    'EMPLOYEEID',
                    'EMPID',
                    'NIK',
                    'BADGENUMBER',
                    'ENROLLNUMBER',
                    'USERID',
                    'CARDNO',
                    'PIN',
                ]);

                $employeeName = $this->firstFilled($row, [
                    'NAME',
                    'NAMA',
                    'EMPLOYEENAME',
                    'USER',
                    'USERNAME',
                ]);

                $dateTimeValue = $this->firstFilled($row, [
                    'WAKTU',
                    'DATETIME',
                    'CHECKINTIME',
                    'CHECKTIME',
                    'LOGTIME',
                    'ATTENDANCETIME',
                    'TIMESTAMP',
                    'TRANSACTIONTIME',
                ]);

                $dateValue = $this->firstFilled($row, [
                    'DATE',
                    'TANGGAL',
                    'CHECKINDATE',
                    'ATTENDANCEDATE',
                    'TRANSACTIONDATE',
                ]);

                $timeValue = $this->firstFilled($row, [
                    'TIME',
                    'JAM',
                    'CLOCKTIME',
                    'CHECKIN',
                    'INTIME',
                ]);

                if ($dateTimeValue === null && $dateValue !== null) {
                    $dateTimeValue = trim($dateValue . ' ' . ($timeValue ?? '00:00:00'));
                }

                return [
                    'employee_id' => $this->normalizeEmployeeId($employeeId),
                    'name' => $this->normalizePersonName($employeeName),
                    'attendance_date' => $this->normalizeDate($dateTimeValue),
                ];
            })
            ->filter(fn(array $row) => $row['attendance_date'] !== null)
            ->values();
    }

    private function isRepeatAttendanceRow(array $row): bool
    {
        $statusCandidates = [
            $this->firstFilled($row, ['STATUS']),
            $this->firstFilled($row, ['STATUSBARU', 'NEWSTATUS']),
            $this->firstFilled($row, ['PENGECUALIAN', 'EXCEPTION']),
        ];

        foreach ($statusCandidates as $status) {
            if ($this->containsRepeatMarker($status)) {
                return true;
            }
        }

        return false;
    }

    private function containsRepeatMarker(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return str_contains(strtoupper($value), 'REPEAT');
    }

    private function readCsvRows(string $filePath): array
    {
        $rows = [];
        $file = new \SplFileObject($filePath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

        foreach ($file as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (count($row) === 1 && ($row[0] === null || trim((string) $row[0]) === '')) {
                continue;
            }

            $rows[] = array_map(fn($value) => is_string($value) ? trim($value) : (string) $value, $row);
        }

        return $rows;
    }

    private function readSpreadsheetRows(string $filePath, string $extension): array
    {
        try {
            $sheets = Excel::toArray(new class implements ToArray {
                public function array(array $array): array
                {
                    return $array;
                }
            }, $filePath);

            return $sheets[0] ?? [];
        } catch (\Throwable $exception) {
            return match ($extension) {
                'csv', 'txt' => $this->readCsvRows($filePath),
                'xlsx' => $this->readXlsxRows($filePath),
                default => [],
            };
        }
    }

    private function readXlsxRows(string $filePath): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw ValidationException::withMessages([
                'attendance_file' => 'File XLSX tidak dapat dibuka.',
            ]);
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sharedStringXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if (!$sheetXml) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Worksheet XLSX tidak ditemukan.',
            ]);
        }

        $sharedStrings = [];

        if ($sharedStringXml) {
            $shared = simplexml_load_string($sharedStringXml);
            if ($shared) {
                foreach ($shared->si as $item) {
                    $text = '';

                    if (isset($item->t)) {
                        $text = (string) $item->t;
                    } elseif (isset($item->r)) {
                        foreach ($item->r as $run) {
                            $text .= (string) ($run->t ?? '');
                        }
                    }

                    $sharedStrings[] = $text;
                }
            }
        }

        $sheet = simplexml_load_string($sheetXml);

        if (!$sheet || !isset($sheet->sheetData)) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Format sheet XLSX tidak valid.',
            ]);
        }

        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $reference = (string) ($cell['r'] ?? '');
                $columnLetters = preg_replace('/[^A-Z]/', '', strtoupper($reference));
                $columnIndex = $this->columnLettersToIndex($columnLetters);

                if ($columnIndex < 0) {
                    continue;
                }

                $type = (string) ($cell['t'] ?? '');
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $sharedIndex = (int) $value;
                    $value = $sharedStrings[$sharedIndex] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = $this->extractInlineStringValue($cell);
                } elseif ($type === 'str') {
                    $value = (string) ($cell->v ?? '');
                }

                $cells[$columnIndex] = trim((string) $value);
            }

            if (count($cells) === 0) {
                continue;
            }

            ksort($cells);
            $maxIndex = max(array_keys($cells));
            $normalizedRow = [];

            for ($index = 0; $index <= $maxIndex; $index++) {
                $normalizedRow[] = $cells[$index] ?? '';
            }

            $rows[] = $normalizedRow;
        }

        return $rows;
    }

    private function extractInlineStringValue(\SimpleXMLElement $cell): string
    {
        if (!isset($cell->is)) {
            return '';
        }

        if (isset($cell->is->t)) {
            return (string) $cell->is->t;
        }

        $text = '';

        foreach ($cell->is->r as $run) {
            $text .= (string) ($run->t ?? '');
        }

        return $text;
    }

    private function columnLettersToIndex(string $letters): int
    {
        if ($letters === '') {
            return -1;
        }

        $index = 0;

        foreach (str_split($letters) as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }

        return $index - 1;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = strtoupper(trim($header));
        return preg_replace('/[^A-Z0-9]/', '', $normalized) ?? '';
    }

    private function firstFilled(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeEmployeeId(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $value) ?? '');
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizePersonName(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (float) $value;

            if ($serial > 10000) {
                try {
                    return Carbon::create(1899, 12, 30)->addDays((int) floor($serial))->toDateString();
                } catch (\Throwable $exception) {
                }
            }
        }

        $formats = [
            'd-M-y g:i A',
            'd-M-y H:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'm/d/Y',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, trim($value))->toDateString();
            } catch (\Throwable $exception) {
            }
        }

        try {
            return Carbon::parse(trim($value))->toDateString();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function toDateOnly(mixed $date): ?string
    {
        if (!$date) {
            return null;
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return substr((string) $date, 0, 10);
    }
}
