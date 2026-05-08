<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMeal extends Model
{
    use HasFactory;

    public const SCOPE_GENERAL = 'general';

    public const SCOPE_EXIT_PERMIT = 'exit_permit';

    protected $appends = [
        'remaining_quantity',
    ];

    protected $fillable = [
        'user_id',
        'order_scope',
        'exit_permit_id',
        'meal_date',
        'meal_type',
        'menu_name',
        'quantity',
        'actual_quantity',
        'visitor_count',
        'schedule_type',
        'day_shift_qty',
        'overtime_day_shift_qty',
        'night_shift_qty',
        'overtime_night_shift_qty',
        'meal_unit_price',
        'local_tax_rate',
        'service_tax_rate',
        'subtotal_amount',
        'local_tax_amount',
        'service_tax_amount',
        'total_amount',
        'notes',
        'status',
        'manager_approved_by',
        'manager_approved_at',
        'md_approved_by',
        'md_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'meal_date' => 'date',
            'quantity' => 'integer',
            'actual_quantity' => 'integer',
            'visitor_count' => 'integer',
            'day_shift_qty' => 'integer',
            'overtime_day_shift_qty' => 'integer',
            'night_shift_qty' => 'integer',
            'overtime_night_shift_qty' => 'integer',
            'meal_unit_price' => 'integer',
            'local_tax_rate' => 'decimal:2',
            'service_tax_rate' => 'decimal:2',
            'subtotal_amount' => 'integer',
            'local_tax_amount' => 'integer',
            'service_tax_amount' => 'integer',
            'total_amount' => 'integer',
            'exit_permit_id' => 'integer',
            'manager_approved_at' => 'datetime',
            'md_approved_at' => 'datetime',
        ];
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, (int) $this->quantity - (int) $this->actual_quantity);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exitPermit(): BelongsTo
    {
        return $this->belongsTo(ExitPermit::class);
    }
}
