<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'background_image',
        'html',
        'font_style',
        'text_color',
        'signature_image',
        'seal_image',
        'is_active',
        'blade_view',
    ];
}
