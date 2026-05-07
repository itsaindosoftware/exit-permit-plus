<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'reimbursement_id',
        'sort_order',
        'ref_document',
        'attachment_path',
        'attachment_original_name',
    ];

    public function reimbursement(): BelongsTo
    {
        return $this->belongsTo(Reimbursement::class);
    }
}
