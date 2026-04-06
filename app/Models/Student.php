<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Student extends Model
{

    use HasFactory, HasUuid, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['student_name', 'user_id', 'preferred_course_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'students';

    protected $primaryKey = 'student_id';

    protected $fillable = [
        'user_id',
        'student_name',
        'preferred_course_id', // تأكد من وجوده هنا

        'enrollment_date',
        'graduation_date',
        'gpa',
    ];

    /**
     * Get the user that owns the student.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get the groups that the student belongs to.
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'student_group', 'student_id', 'group_id')
            ->withTimestamps();
    }

    /**
     * Get the group relationship records.
     */
    public function studentGroups()
    {
        return $this->hasMany(StudentGroup::class, 'student_id', 'student_id');
    }

    /**
     * Get the assignment submissions for the student.
     */
    public function assignmentSubmissions()
    {
        return $this->hasMany(AssignmentSubmission::class, 'student_id', 'student_id');
    }

    /**
     * Get the attendance records for the student.
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id', 'student_id');
    }

    /**
     * Get the ratings for the student.
     */
    public function ratings()
    {
        return $this->hasMany(Rating::class, 'student_id', 'student_id');
    }

    /**
     * Get the bookings for the student.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'student_id', 'student_id');
    }

    /**
     * Get the invoices for the student.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'student_id', 'student_id');
    }

    /**
     * Get the waiting group associations.
     */
    public function waitingGroups()
    {
        return $this->belongsToMany(WaitingGroup::class, 'waiting_students', 'student_id', 'waiting_group_id')
            ->withPivot(['placement_exam_grade', 'assigned_level', 'notes', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Get the waiting student records.
     */
    public function waitingStudents()
    {
        return $this->hasMany(WaitingStudent::class, 'student_id', 'student_id');
    }

    /**
     * Accessor for whether student is in any waiting group.
     */
    public function getIsInWaitingGroupAttribute()
    {
        return $this->waitingStudents()->exists();
    }

    /**
     * Check if student has unpaid invoices.
     */
    public function hasUnpaidInvoices()
    {
        try {
            if ($this->invoices->isEmpty()) return false;

            return $this->invoices->some(function ($invoice) {
                return $invoice->status != 'paid' || $invoice->amount_paid < $invoice->amount;
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in hasUnpaidInvoices: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Consolidated Payment Status Accessor.
     */
    public function getPaymentStatusAttribute()
    {
        try {
            if (!$this->relationLoaded('invoices')) $this->load('invoices');
            if ($this->invoices->isEmpty()) return 'new_student';

            $totalPaid = $this->invoices->sum('amount_paid');
            $totalRequired = $this->invoices->sum('amount');

            if ($totalPaid >= $totalRequired) return 'paid';
            if ($totalPaid == 0) return 'unpaid';
            return 'partial';
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in payment_status attribute: '.$e->getMessage());
            return 'error';
        }
    }

    /**
     * Current preferred course relationship.
     */
    public function preferredCourse()
    {
        return $this->belongsTo(Course::class, 'preferred_course_id', 'course_id');
    }

    /**
     * Dynamic Preferred Course Accessor (with meta fallback).
     */
    public function getPreferredCourseAttribute()
    {
        // 1. Direct relationship
        if ($this->preferred_course_id) {
            $course = $this->preferredCourse;
            if ($course) return $course;
        }

        // 2. Fallback to Meta
        $metaCourseId = StudentMeta::getValue($this->student_id, 'preferred_course_id');
        if ($metaCourseId) {
            $course = Course::find($metaCourseId);
            if ($course) return $course;
        }

        // 3. Fallback to latest group enrollment
        $latestGroup = $this->groups()->latest()->first();
        if ($latestGroup && $latestGroup->course) {
            return $latestGroup->course;
        }

        return null;
    }

    /**
     * Update preferred course and keep meta in sync.
     */
    public function updatePreferredCourse($courseId)
    {
        $this->update(['preferred_course_id' => $courseId]);
        StudentMeta::setValue($this->student_id, 'preferred_course_id', $courseId);
        return true;
    }
    // In App\Models\Student - Add these methods:

    /**
     * Get total paid amount with proper calculations
     */
    public function getTotalPaidAttribute()
    {
        try {
            return $this->invoices()->sum('amount_paid');
        } catch (\Exception $e) {
            Log::error('Error in getTotalPaidAttribute: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Get total due amount
     */
    public function getTotalDueAttribute()
    {
        try {
            $totalDue = 0;
            foreach ($this->invoices as $invoice) {
                // Calculate final amount after discount
                $finalAmount = $invoice->amount - ($invoice->discount_amount ?: 0);
                if ($finalAmount > $invoice->amount_paid) {
                    $totalDue += ($finalAmount - $invoice->amount_paid);
                }
            }

            return $totalDue;
        } catch (\Exception $e) {
            Log::error('Error in getTotalDueAttribute: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Get total discount
     */
    public function getTotalDiscountAttribute()
    {
        try {
            return $this->invoices()->sum('discount_amount');
        } catch (\Exception $e) {
            Log::error('Error in getTotalDiscountAttribute: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Get payment completion percentage
     */
    public function getPaymentCompletionAttribute()
    {
        try {
            $totalAmount = $this->invoices()->sum('amount');
            $totalDiscount = $this->total_discount;
            $finalAmount = $totalAmount - $totalDiscount;
            $totalPaid = $this->total_paid;

            if ($finalAmount > 0) {
                return round(($totalPaid / $finalAmount) * 100, 2);
            }

            return 100;
        } catch (\Exception $e) {
            Log::error('Error in getPaymentCompletionAttribute: '.$e->getMessage());

            return 0;
        }
    }
    /**
     * Get the course selections for the student.
     */
    public function courseSelections()
    {
        return $this->hasMany(StudentCourseSelection::class, 'student_id', 'student_id');
    }
}
