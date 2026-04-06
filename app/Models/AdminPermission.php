<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminPermission extends Model
{
    use HasFactory;

    protected $table = 'admin_permissions';

    protected $fillable = ['permission_key', 'label', 'description', 'is_full_only'];

    public static function booted()
    {
        // Deprecated: admin permissions have been migrated to admin_types flags.
        // Throw if used to help find lingering references during development.
        throw new \RuntimeException('AdminPermission model is deprecated. Use AdminType flags instead.');
    }
}
