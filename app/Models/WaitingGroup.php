<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaitingGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_name',
        'course_id',
        'subcourse_id',
        'description',
        'max_students',
        'status',
        'created_by',
    ];

    protected $casts = [
        'max_students' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * علاقة المجموعة مع الكورس
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    /**
     * علاقة المجموعة مع الصب كورس
     */
    public function subcourse()
    {
        return $this->belongsTo(SubCourse::class, 'subcourse_id', 'subcourse_id');
    }

    /**
     * علاقة المجموعة مع منشئها
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * علاقة المجموعة مع الطلاب المسجلين فيها
     */
    public function waitingStudents()
    {
        return $this->hasMany(WaitingStudent::class, 'waiting_group_id');
    }

    /**
     * علاقة المجموعة مع الطلاب (من خلال waitingStudents)
     */
    public function students()
    {
        return $this->belongsToMany(Student::class, 'waiting_students', 'waiting_group_id', 'student_id')
            ->withPivot(['placement_exam_grade', 'assigned_level', 'notes', 'status'])
            ->withTimestamps();
    }

    /**
     * علاقة المجموعة مع waitingGroups (إزالة أي علاقة مع bookings)
     */
    public function waitingGroups()
    {
        // هذه العلاقة يجب أن تكون فارغة أو تتبع العلاقة الصحيحة
        return $this->hasMany(WaitingStudent::class, 'waiting_group_id');
    }

    /**
     * إزالة أي علاقة مع bookings لأنك لا تتعامل معها
     */
    // لا تضيف علاقة bookings إطلاقاً

    /**
     * عدد الطلاب الحاليين في المجموعة
     */
    public function getStudentsCountAttribute()
    {
        return $this->waitingStudents()->count();
    }

    /**
     * هل المجموعة ممتلئة؟
     */
    public function getIsFullAttribute()
    {
        return $this->students_count >= $this->max_students;
    }

    /**
     * الطلاب النشطين (بس status = waiting أو contacted)
     */
    public function activeStudents()
    {
        return $this->waitingStudents()->whereIn('status', ['waiting', 'contacted']);
    }

    /**
     * الطلاب المرفوضين
     */
    public function rejectedStudents()
    {
        return $this->waitingStudents()->where('status', 'rejected');
    }

    /**
     * الطلاب المعتمدين
     */
    public function approvedStudents()
    {
        return $this->waitingStudents()->where('status', 'approved');
    }

    /**
     * عرض حالة المجموعة مع لون
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'active' => '<span class="badge bg-success">نشط</span>',
            'inactive' => '<span class="badge bg-secondary">غير نشط</span>',
            'full' => '<span class="badge bg-warning">ممتلئ</span>',
        ];

        return $badges[$this->status] ?? '<span class="badge bg-secondary">غير معروف</span>';
    }

    /**
     * عرض نموذج إنشاء مجموعة جديدة من مجموعة انتظار
     */
    public function createFromWaiting($waitingGroupId)
    {
        try {
            // جلب مجموعة الانتظار
            $waitingGroup = WaitingGroup::with([
                'course',
                'subcourse',
                'waitingStudents.student.user',
                'waitingStudents.student.studentGroups.group',
            ])->findOrFail($waitingGroupId);

            // جلب الطلاب من مجموعة الانتظار
            $waitingStudents = $waitingGroup->waitingStudents;

            // استخراج IDs الطلاب
            $studentIds = $waitingStudents->pluck('student_id')->toArray();

            // جلب بيانات الطلاب الكاملة
            $students = Student::whereIn('student_id', $studentIds)
                ->with(['user', 'invoices'])
                ->get();

            // جلب باقي البيانات المطلوبة للنموذج
            $courses = Course::all();
            $teachers = Teacher::all();
            $rooms = Room::where('status', 'active')->get();

            // حساب بيانات التكلفة والتقديرات
            foreach ($students as $student) {
                $student->total_paid = $student->invoices()->sum('amount_paid');
                $student->total_required = $student->invoices()->sum('amount');
                $student->has_debt = $student->total_paid < $student->total_required;
            }

            // إعداد بيانات افتراضية من مجموعة الانتظار
            $defaultData = [
                'group_name' => $waitingGroup->group_name.' - مفعلة',
                'course_id' => $waitingGroup->course_id,
                'subcourse_id' => $waitingGroup->subcourse_id,
                'description' => "تم التفعيل من مجموعة الانتظار: {$waitingGroup->group_name}",
                'students' => $studentIds,
                'waiting_group_id' => $waitingGroupId, // إضافة معرف مجموعة الانتظار
            ];

            return view('groups.create', compact(
                'courses',
                'teachers',
                'students',
                'rooms',
                'defaultData',
                'waitingGroup'
            ));

        } catch (\Exception $e) {
            \Log::error('Error creating group from waiting: '.$e->getMessage());

            return redirect()->route('waiting-groups.index')
                ->with('error', 'حدث خطأ في تحويل مجموعة الانتظار: '.$e->getMessage());
        }
    }
}
