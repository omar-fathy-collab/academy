<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class AdminVault extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'profit_percentage',
        'is_active',
        'total_earned',
        'total_withdrawn',
        'last_calculation',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    protected $appends = ['balance', 'available_balance', 'actual_capital'];

    /**
     * Get the completed_at attribute as Carbon instance.
     */
    protected function completedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? \Carbon\Carbon::parse($value) : null,
        );
    }

    /**
     * Get the canceled_at attribute as Carbon instance.
     */
    protected function canceledAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? \Carbon\Carbon::parse($value) : null,
        );
    }

    /**
     * Get the approved_at attribute as Carbon instance.
     */
    protected function approvedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? \Carbon\Carbon::parse($value) : null,
        );
    }

    // علاقات
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(AdminWithdrawal::class);
    }

    public function capitalAdditions()
    {
        return $this->hasMany(CapitalAddition::class, 'added_by', 'user_id');
    }

    // Accessors - حساب الرصيد الحقيقي
    public function getActualCapitalAttribute()
    {
        return $this->capitalAdditions()->sum('amount');
    }

    public function getBalanceAttribute()
    {
        $actualCapital = $this->getActualCapitalAttribute();

        return ($actualCapital + $this->total_earned) - $this->total_withdrawn;
    }

    // للحفاظ على التوافق

    public function canWithdraw($amount)
    {
        return $this->balance >= $amount && $this->is_active;
    }

    public function addProfit($amount)
    {
        $this->total_earned += $amount;
        $this->last_calculation = now();
        $this->save();
    }

    public function withdraw($amount)
    {
        if (! $this->canWithdraw($amount)) {
            throw new \Exception('Insufficient available balance');
        }

        $this->total_withdrawn += $amount;
        $this->save();
    }

    public function getAvailableWithdrawalBalanceAttribute()
    {
        // هذا هو الرصيد الحقيقي الذي يمكن سحبه (الربح فقط)
        return $this->total_earned - $this->total_withdrawn;
    }

    public function getPendingProfitAttribute()
    {
        // استخدام FinancialService بشكل مباشر بدلاً من Controller لتجنب الثغرات والاعتماديات الدائرية
        $netProfit = app(\App\Services\FinancialService::class)->calculateNetProfit();

        return ($netProfit * $this->profit_percentage) / 100;
    }

    /**
     * حساب الرصيد المتاح للسحب (لحظي)
     */
    public function getAvailableBalanceAttribute()
    {
        // الربح المستحق - المسحوبات
        return max($this->getPendingProfitAttribute() - $this->total_withdrawn, 0);
    }

    /**
     * هل هناك ربح معلق؟
     */
    public function getHasPendingProfitAttribute()
    {
        return $this->getPendingProfitAttribute() > $this->total_withdrawn;
    }
}
