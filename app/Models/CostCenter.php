<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cost_center_sap',
        'desc_cost_c',
    ];

    public function exitPermits(): HasMany
    {
        return $this->hasMany(ExitPermit::class);
    }
}
