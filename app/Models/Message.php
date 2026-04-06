<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Message extends Model
{
    protected $table = 'group_messages';

    protected $fillable = [
        'group_id',
        'user_id',
        'message',
        'file_path',
        'file_name',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    // Relationships
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Encrypt message before saving
    public function setMessageAttribute($value)
    {
        $this->attributes['message'] = $value ? Crypt::encryptString($value) : null;
    }

    // Decrypt message when retrieving
    public function getMessageAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Scope for group messages
    public function scopeForGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    // Scope for recent messages
    public function scopeRecent($query, $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
