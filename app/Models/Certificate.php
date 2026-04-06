<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'certificate_type',
        'course_id',
        'group_id',
        'issued_by',
        'certificate_number',
        'issue_date',
        'attendance_percentage',
        'quiz_average',
        'final_rating',
        'file_path',
        'status',
        'remarks',
    ];

    protected $casts = [
        'issue_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function template()
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }
}
