<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DeletedInvoiceLog extends Model
{
    use HasUuid;

    protected $table = 'deleted_invoices_log';

    protected $fillable = [
        'original_invoice_id',
        'invoice_number',
        'student_id',
        'group_id',
        'old_group_id',
        'new_group_id',
        'amount',
        'amount_paid',
        'discount_amount',
        'discount_percent',
        'status_before_deletion',
        'deletion_reason',
        'deleted_by',
        'original_created_at',
        'deleted_at',
    ];

    protected $casts = [
        'original_created_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
