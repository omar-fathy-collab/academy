<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    const ADMIN_ID = 1;

    const TEACHER_ID = 2;

    const STUDENT_ID = 3;

    protected $table = 'roles';

    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'role_name',
    ];

    public $timestamps = true;
}
