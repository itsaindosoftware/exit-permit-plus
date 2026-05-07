<?php

namespace App\Models;

use App\Models\ScheduleCarArrangementLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExitPermit extends Model
{
    use HasFactory;

    public const POST_MD_PATH_MEAL = 'meal';

    public const POST_MD_PATH_REIMBURSEMENT = 'reimbursement';

    public const EXIT_TYPE_BUSINESS_TRIP = 'business_trip';

    public const EXIT_TYPE_SICK = 'sick';

    public const EXIT_TYPES = [
        self::EXIT_TYPE_BUSINESS_TRIP,
        self::EXIT_TYPE_SICK,
    ];

    public const REIMBURSEMENT_AMOUNTS = [12000, 13000];

    protected $fillable = [
        'user_id',
        'hr_approver_id',
        'permit_date',
        'start_time',
        'end_time',
        'destination',
        'exit_type',
        'order_car',
        'vehicle_plate',
        'driver_name',
        'returned_to_office',
        'eligible_for_meal',
        'reimbursement_amount',
        'reason',
        'notes',
        'attachment_path',
        'attachment_original_name',
        'status',
        'manager_approved_by',
        'manager_approved_at',
        'md_approved_by',
        'md_approved_at',
        'hr_verified_by',
        'hr_verified_at',
        'attendance_checked_by',
        'attendance_checked_at',
        'has_valid_checkin',
        'post_md_path',
    ];

    protected function casts(): array
    {
        return [
            'permit_date' => 'date',
            'order_car' => 'boolean',
            'returned_to_office' => 'boolean',
            'eligible_for_meal' => 'boolean',
            'manager_approved_at' => 'datetime',
            'md_approved_at' => 'datetime',
            'hr_verified_at' => 'datetime',
            'attendance_checked_at' => 'datetime',
            'has_valid_checkin' => 'boolean',
        ];
    }

    public function syncBusinessRules(): static
    {
        $this->eligible_for_meal = $this->qualifiesForMeal();

        return $this;
    }

    public function qualifiesForMeal(): bool
    {
        if (!$this->returned_to_office || !$this->start_time || !$this->end_time) {
            return false;
        }

        $startTime = Carbon::createFromFormat('H:i', substr($this->start_time, 0, 5));
        $endTime = Carbon::createFromFormat('H:i', substr($this->end_time, 0, 5));

        $leftBeforeNoonAndReturnedBeforeLunch = $startTime->lt(Carbon::createFromTimeString('12:00'))
            && $endTime->lte(Carbon::createFromTimeString('12:00'));

        $leftAfterOnePmAndReturned = $startTime->gte(Carbon::createFromTimeString('13:00'));

        return $leftBeforeNoonAndReturnedBeforeLunch || $leftAfterOnePmAndReturned;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approver_id');
    }

    public function managerApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function mdApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'md_approved_by');
    }

    public function hrVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_verified_by');
    }

    public function attendanceChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_checked_by');
    }

    public function reimbursements(): HasMany
    {
        return $this->hasMany(Reimbursement::class);
    }

    public function orderMeals(): HasMany
    {
        return $this->hasMany(OrderMeal::class);
    }

    public function requestors(): HasMany
    {
        return $this->hasMany(ExitPermitRequestor::class)->orderBy('row_number');
    }

    public function scheduleCarArrangementLogs(): HasMany
    {
        return $this->hasMany(ScheduleCarArrangementLog::class)->latest('arranged_at');
    }
}
