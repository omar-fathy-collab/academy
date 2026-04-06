<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupEnrollmentRequest extends Model
{
    protected $fillable = [
        'user_id',
        'group_id',
        'screenshot_path',
        'status',
        'notes',
        'viewed_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }
}
