<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DeletedPaymentArchive extends Model
{
    use HasUuid;

    protected $table = 'deleted_payments_archive';
    public $timestamps = false;

    protected $fillable = [
        'original_payment_id',
        'invoice_id',
        'amount',
        'payment_method',
        'transaction_id',
        'payment_date',
        'deleted_reason',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'deleted_at' => 'datetime',
    ];
}
