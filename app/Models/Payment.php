<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{

    use \Illuminate\Database\Eloquent\Factories\HasFactory, \App\Traits\HasUuid, SoftDeletes;

    public $timestamps = true;

    protected $table = 'payments';

    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_method',
        'notes',
        'receipt_image',
        'confirmed_by',
        'whatsapp_sent',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    /**
     * Get the invoice that owns the payment.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }

    /**
     * Get the user who confirmed the payment.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'confirmed_by', 'id');
    }
}
