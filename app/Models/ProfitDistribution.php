<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfitDistribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'total_net_profit',
        'distribution_date',
        'distribution_details',
        'distributed_by',
    ];

    protected $casts = [
        'total_net_profit' => 'decimal:2',
        'distribution_date' => 'date',
        'distribution_details' => 'array',
    ];

    public function distributor()
    {
        return $this->belongsTo(User::class, 'distributed_by');
    }
}
