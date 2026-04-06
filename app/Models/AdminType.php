<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminType extends Model
{
    use HasFactory;

    protected $table = 'admin_types';

    protected $fillable = [
        'name',
        'label',
        'can_view_profits',
        'can_manage_admins',
        'can_manage_finances',
        'created_at',
        'updated_at',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'admin_type_id');
    }
}
