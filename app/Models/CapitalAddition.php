<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CapitalAddition extends Model
{
    use HasFactory, HasUuid;

    // Table name (optional لو الاسم مطابق للكونفنشين)
    protected $table = 'capital_additions';

    // Mass assignable fields
    protected $fillable = [
        'amount',
        'description',
        'added_by',
        'addition_date',
    ];

    // Casts
    protected $casts = [
        'amount' => 'decimal:2',
        'addition_date' => 'date',
    ];

    /**
     * Relation: who added the capital
     */
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
