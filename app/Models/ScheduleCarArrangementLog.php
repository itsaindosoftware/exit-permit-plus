<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleCarArrangementLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'exit_permit_id',
        'arranged_by',
        'arranged_at',
        'action',
        'car_id',
        'driver_id',
        'vehicle_plate',
        'driver_name',
    ];

    protected function casts(): array
    {
        return [
            'arranged_at' => 'datetime',
        ];
    }

    public function exitPermit(): BelongsTo
    {
        return $this->belongsTo(ExitPermit::class);
    }

    public function arranger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arranged_by');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
