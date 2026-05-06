<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitPermitRequestor extends Model
{
    use HasFactory;

    protected $fillable = [
        'exit_permit_id',
        'row_number',
        'name',
        'employee_id',
        'position',
        'department',
        'reimburs_lunch_box',
    ];

    public function exitPermit(): BelongsTo
    {
        return $this->belongsTo(ExitPermit::class);
    }
}
