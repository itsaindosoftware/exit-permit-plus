<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceSupplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_name',
        'meal_unit_price',
        'local_tax_rate',
        'service_tax_rate',
        'effective_date',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'meal_unit_price' => 'integer',
            'local_tax_rate' => 'decimal:2',
            'service_tax_rate' => 'decimal:2',
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}