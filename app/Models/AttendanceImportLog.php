<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceImportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'exit_permit_id',
        'user_id',
        'source_disk',
        'source_path',
        'source_file_name',
        'import_type',
        'imported_at',
        'total_requestors',
        'matched_count',
        'has_valid_checkin',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'has_valid_checkin' => 'boolean',
        ];
    }

    public function exitPermit(): BelongsTo
    {
        return $this->belongsTo(ExitPermit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
