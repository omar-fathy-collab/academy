<?php

// app/Models/Room.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $table = 'rooms';

    protected $primaryKey = 'room_id';

    public $timestamps = true;

    protected $fillable = [
        'room_name',
        'capacity',
        'location',
        'facilities',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the schedules for this room
     */
    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'room_id', 'room_id');
    }
}
