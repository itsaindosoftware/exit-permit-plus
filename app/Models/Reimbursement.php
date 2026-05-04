<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reimbursement extends Model
{
    use HasFactory;

    public const STATUS_PENDING_MANAGER = 'pending_manager';

    public const STATUS_PENDING_MD = 'pending_md';

    public const STATUS_PENDING_RATNA = 'pending_ratna';

    public const STATUS_SUBMITTED_TO_ACCOUNTING = 'submitted_to_accounting';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING_MANAGER,
        self::STATUS_PENDING_MD,
        self::STATUS_PENDING_RATNA,
        self::STATUS_SUBMITTED_TO_ACCOUNTING,
        self::STATUS_FINISHED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'exit_permit_id',
        'user_id',
        'request_date',
        'amount',
        'description',
        'status',
        'manager_approved_by',
        'manager_approved_at',
        'md_approved_by',
        'md_approved_at',
        'ratna_submitted_by',
        'ratna_submitted_at',
        'accounting_processed_by',
        'accounting_processed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'amount' => 'integer',
            'manager_approved_at' => 'datetime',
            'md_approved_at' => 'datetime',
            'ratna_submitted_at' => 'datetime',
            'accounting_processed_at' => 'datetime',
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

    public function managerApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function mdApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'md_approved_by');
    }

    public function ratnaSubmitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratna_submitted_by');
    }

    public function accountingProcessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accounting_processed_by');
    }
}
