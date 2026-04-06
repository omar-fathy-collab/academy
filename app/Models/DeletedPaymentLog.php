<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DeletedPaymentLog extends Model
{
    use HasUuid;

    protected $table = 'deleted_payments_log';

    protected $fillable = [
        'original_payment_id',
        'invoice_id',
        'amount',
        'payment_method',
        'transaction_id',
        'payment_date',
        'deleted_reason',
        'deleted_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
    ];
}
