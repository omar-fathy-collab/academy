<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DeletedInvoiceArchive extends Model
{
    use HasUuid;

    protected $table = 'deleted_invoices_archive';
    public $timestamps = false;

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
        'notes',
        'deleted_reason',
        'transfer_date',
        'deleted_by',
        'original_created_at',
        'deleted_at',
    ];

    protected $casts = [
        'original_created_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
