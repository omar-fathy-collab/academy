<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasUuid;
    protected $table = 'activities';

    protected $fillable = [
        'user_id', 'subject_type', 'subject_id', 'action', 
        'description', 'changes', 'extra', 'ip', 'user_agent'
    ];

    protected $casts = [
        'changes' => 'array',
        'extra' => 'array',
        'created_at' => 'datetime',
    ];

    protected $appends = ['old_data', 'new_data', 'model', 'model_id', 'user_name'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Safe accessors with fallbacks
    public function getOldDataAttribute()
    {
        $changes = $this->getAttribute('changes');

        if (is_string($changes)) {
            $changes = json_decode($changes, true);
        }

        if (is_array($changes)) {
            return $changes['before'] ?? $changes['old'] ?? [];
        }

        return [];
    }

    public function getNewDataAttribute()
    {
        $changes = $this->getAttribute('changes');

        if (is_string($changes)) {
            $changes = json_decode($changes, true);
        }

        if (is_array($changes)) {
            return $changes['after'] ?? $changes['new'] ?? [];
        }

        return [];
    }

    public function getModelAttribute()
    {
        if (isset($this->subject_type) && $this->subject_type) {
            return class_basename($this->subject_type);
        }

        return 'Unknown';
    }

    public function getModelIdAttribute()
    {
        return $this->subject_id ?? 'N/A';
    }

    public function getUserNameAttribute()
    {
        if (! $this->user_id) {
            return 'System';
        }

        // Load the user relationship if not already loaded
        if (! $this->relationLoaded('user')) {
            $this->load('user');
        }

        if ($this->user) {
            // Load profile relationship if needed
            if (! $this->user->relationLoaded('profile')) {
                $this->user->load('profile');
            }

            return $this->user->profile->nickname ??
                   $this->user->username ??
                   $this->user->email ??
                   'Unknown User';
        }

        return 'System';
    }

    /**
     * Eager load user and profile by default for better performance
     */
    protected $with = ['user.profile'];
}
