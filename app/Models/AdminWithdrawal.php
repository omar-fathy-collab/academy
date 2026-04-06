<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminWithdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vault_id',
        'amount',
        'status',
        'notes',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vault()
    {
        return $this->belongsTo(AdminVault::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approve($approverId, $notes = null)
    {
        $this->status = 'approved';
        $this->approved_by = $approverId;
        $this->approved_at = now();
        if ($notes) {
            $this->notes = $notes;
        }
        $this->save();
    }

    public function complete()
    {
        $this->status = 'completed';
        $this->save();
    }

    public function reject($notes = null)
    {
        $this->status = 'rejected';
        if ($notes) {
            $this->notes = $notes;
        }
        $this->save();
    }
}
