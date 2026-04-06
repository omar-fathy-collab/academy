<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryPaymentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'item_id',
        'item_type',
        'screenshot_path',
        'amount',
        'status',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the item (Video or Book)
     */
    public function item()
    {
        if ($this->item_type === 'video') {
            return $this->belongsTo(Video::class, 'item_id');
        }
        return $this->belongsTo(Book::class, 'item_id');
    }
}
