<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'imported_by',
        'imported_at',
        'success_count',
        'failed_count',
    ];

    public $timestamps = true;
}
