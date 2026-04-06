<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{

    use \Illuminate\Database\Eloquent\Factories\HasFactory, \App\Traits\HasUuid, SoftDeletes;

    public $timestamps = true;
    protected $appends = ['final_amount', 'balance_due'];

    protected $table = 'invoices';

    protected $primaryKey = 'invoice_id';

    protected $fillable = [
        'student_id',
        'group_id',
        'invoice_number',
        'description',
        'amount',
        'discount_amount',
        'amount_paid',
        'discount_percent',
        'due_date',
        'status',
        'public_token',
        'payment_screenshot',
        'payment_notes',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($invoice) {
            if (!$invoice->public_token) {
                $invoice->public_token = \Illuminate\Support\Str::random(40);
            }
        });
    }

    protected $casts = [
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2', // تم إضافته
        'amount_paid' => 'decimal:2',
        'due_date' => 'date',
    ];

    /**
     * Get the student that owns the invoice.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /**
     * Get the group that owns the invoice.
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    /**
     * Get the payments for the invoice.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'invoice_id', 'invoice_id');
    }

    /**
     * Calculate final amount after discount
     */
    public function getFinalAmountAttribute()
    {
        // استخدام discount_amount إذا كان موجوداً، وإلا حساب من discount_percent
        if ($this->discount_amount > 0) {
            return $this->amount - $this->discount_amount;
        }

        return $this->amount - ($this->amount * ($this->discount_percent / 100));
    }

    /**
     * Calculate balance due
     */
    public function getBalanceDueAttribute()
    {
        return $this->final_amount - $this->amount_paid;
    }

    /**
     * Check if invoice is fully paid
     */
    public function getIsPaidAttribute()
    {
        return $this->balance_due <= 0;
    }
}
