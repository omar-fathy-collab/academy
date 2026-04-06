<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionMaterial extends Model
{

    use \Illuminate\Database\Eloquent\Factories\HasFactory, \App\Traits\HasUuid;

    protected $table = 'session_materials';

    protected $fillable = [
        'session_id',
        'uploaded_by',
        'original_name',
        'file_path',
        'mime_type',
        'size',
    ];

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id');
    }
}
