<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $table = 'expenses';

    protected $primaryKey = 'expense_id';

    protected $fillable = [
        'category',
        'payment_method', // تم الإضافة
        'amount',
        'is_approved',
        'description',
        'expense_date',
        'recorded_by',
    ];

    // تم تفعيل الـ timestamps لتطابق الداتابيز
    public $timestamps = true;

    protected $dates = [
        'expense_date',
    ];

    // تعريف القيم الافتراضية للـ categories و payment methods
    public static function getCategories()
    {
        return [
            'office_supplies' => 'Office Supplies',
            'utilities' => 'Utilities',
            'rent' => 'Rent',
            'salaries' => 'Salaries',
            'marketing' => 'Marketing',
            'travel' => 'Travel',
            'equipment' => 'Equipment',
            'maintenance' => 'Maintenance',
            'software' => 'Software',
            'training' => 'Training',
            'other' => 'Other',
        ];
    }

    public static function getPaymentMethods()
    {
        return [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'check' => 'Check',
            'credit_card' => 'Credit Card',
            'vodafone_cash' => 'Vodafone Cash',
            'instapay' => 'Instapay',
            'voucher' => 'Voucher',
            'online_payment' => 'Online Payment',
            'other' => 'Other',
        ];
    }

    /**
     * Get the user who created the expense.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'recorded_by', 'id');
    }

    /**
     * Scope for approved expenses
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', 1);
    }

    /**
     * Scope for pending expenses
     */
    public function scopePending($query)
    {
        return $query->where('is_approved', 0);
    }

    /**
     * Get category name
     */
    public function getCategoryNameAttribute()
    {
        $categories = self::getCategories();

        return $categories[$this->category] ?? $this->category;
    }

    /**
     * Get payment method name
     */
    public function getPaymentMethodNameAttribute()
    {
        $methods = self::getPaymentMethods();

        return $methods[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * Set the category attribute with length validation
     */
    public function setCategoryAttribute($value)
    {
        // تقصير النص إلى 50 حرف كحد أقصى
        $this->attributes['category'] = substr($value, 0, 50);
    }

    /**
     * Set the payment_method attribute
     */
    public function setPaymentMethodAttribute($value)
    {
        $this->attributes['payment_method'] = $value ? substr($value, 0, 50) : null;
    }
}
