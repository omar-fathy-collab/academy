<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'session_id',
        'group_id',
        'title',
        'description',
        'file_path',
        'thumbnail_url',
        'visibility',
        'price',
        'is_library',
        'status',
    ];

    public function groups()
    {
        return $this->belongsToMany(\App\Models\Group::class, 'book_group', 'book_id', 'group_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }
}
