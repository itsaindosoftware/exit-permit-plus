<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->timestamps();
        });

        $this->seedCostCentersFromKaryawan();
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }

    private function seedCostCentersFromKaryawan(): void
    {
        try {
            $connectionName = (string) config('attendance.requestor_source_connection', config('database.default'));
            $configuredSourceTable = trim((string) config('attendance.requestor_source_table', ''));
            $tableCandidates = collect(array_merge(
                ['karyawan'],
                $configuredSourceTable !== '' ? [$configuredSourceTable] : [],
                (array) config('attendance.requestor_source_tables', []),
                ['employees', 'absensi_karyawan'],
            ))
                ->map(fn($table) => trim((string) $table))
                ->filter(fn(string $table) => $table !== '')
                ->unique()
                ->values();

            $sourceTable = $tableCandidates
                ->first(fn(string $table) => Schema::connection($connectionName)->hasTable($table));

            if (!$sourceTable) {
                return;
            }

            $columns = collect(Schema::connection($connectionName)->getColumnListing($sourceTable))
                ->map(fn($column) => strtolower((string) $column))
                ->values();

            $findColumn = function (array $candidates) use ($columns): ?string {
                foreach ($candidates as $candidate) {
                    if ($columns->contains(strtolower($candidate))) {
                        return (string) $columns->first(
                            fn($column) => strtolower((string) $column) === strtolower($candidate),
                        );
                    }
                }

                return null;
            };

            $departmentColumn = $findColumn(['department', 'departemen', 'dept', 'department_name', 'bagian']);

            if (!$departmentColumn) {
                return;
            }

            $now = now();
            $rows = DB::connection($connectionName)
                ->table($sourceTable)
                ->select($departmentColumn . ' as department_name')
                ->whereNotNull($departmentColumn)
                ->get()
                ->map(fn($row) => trim((string) ($row->department_name ?? '')))
                ->filter(fn(string $name) => $name !== '')
                ->unique(fn(string $name) => strtolower($name))
                ->values()
                ->map(fn(string $name) => [
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            if (count($rows) > 0) {
                DB::table('cost_centers')->insert($rows);
            }
        } catch (\Throwable) {
            // Keep migration resilient when external attendance source is unavailable.
        }
    }
};
