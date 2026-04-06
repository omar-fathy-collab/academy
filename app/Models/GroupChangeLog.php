<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class GroupChangeLog extends Model
{
    use HasUuid;

    protected $table = 'group_change_logs';

    protected $fillable = [
        'group_id',
        'changed_by',
        'old_data',
        'new_data',
        'changes',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'changes' => 'array',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by', 'id');
    }
}
