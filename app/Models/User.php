<?php

namespace App\Models;


use App\Traits\Encryptable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{

    use Encryptable;
    use HasFactory, Notifiable, HasUuid, LogsActivity;
    use HasRoles;

    public $timestamps = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['username', 'email', 'role_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $fillable = [
        'username',
        'email',
        'pass',
        'role_id',
        'admin_type_id',
        'is_active',
        'welcome_sent',
    ];

    protected $hidden = [
        'pass',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'role_id' => 'integer',
        'created_at' => 'datetime', // أضف هذا السطر
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'isAdmin',
        'isAdminFull',
        'isStudent',
        'isTeacher',
    ];

    /**
     * Attributes that should be encrypted at rest.
     * These will be transparently encrypted/decrypted by the Encryptable trait.
     */
    protected $encryptable = [];
    
    /**
     * Get the password for authentication.
     */
    public function getAuthPassword()
    {
        return $this->pass;
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName()
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Get the role that belongs to the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    /**
     * Admin type relation (full / partial)
     */
    public function adminType()
    {
        return $this->belongsTo(AdminType::class, 'admin_type_id', 'id');
    }

    /**
     * Get the profile that belongs to the user.
     */
    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id', 'id');
    }

    /**
     * Get the teacher record if user is a teacher.
     */
    public function teacher()
    {
        return $this->hasOne(Teacher::class, 'user_id', 'id');
    }

    /**
     * Get the student record if user is a student.
     */
    public function student()
    {
        return $this->hasOne(Student::class, 'user_id', 'id');
    }

    /**
     * Admin permissions relation (many-to-many)
     */
    // Permissions are now derived from admin_type flags on admin_types table.

    /**
     * Get the full name of the user.
     */
    public function getNameAttribute()
    {
        return $this->profile ? $this->profile->nickname : $this->username;
    }

    /**
     * Role & Permission Helpers
     */
    public function isAdmin()
    {
        return $this->getIsAdminAttribute();
    }

    public function getIsAdminAttribute()
    {
        return $this->hasRole(['admin', 'super-admin']) || ($this->role && strtolower($this->role->name) === 'admin');
    }

    public function isAdminFull()
    {
        return $this->getIsAdminFullAttribute();
    }

    public function getIsAdminFullAttribute()
    {
        return $this->hasRole('super-admin') || ($this->isAdmin() && $this->adminType && $this->adminType->name === 'full');
    }

    public function isAdminPartial()
    {
        return $this->isAdmin() && $this->adminType && $this->adminType->name === 'partial';
    }

    public function isStudent()
    {
        return $this->getIsStudentAttribute();
    }

    public function getIsStudentAttribute()
    {
        return $this->role_id == 3 || $this->hasRole('student');
    }

    public function isTeacher()
    {
        return $this->getIsTeacherAttribute();
    }

    public function getIsTeacherAttribute()
    {
        return $this->role_id == 2 || $this->hasRole('teacher');
    }

    public function isSecretary()
    {
        return $this->role_id == 4 || $this->hasRole('secretary');
    }

    public function isAccountant()
    {
        return $this->role_id == 4 || $this->hasRole('accountant');
    }

    /**
     * Check whether user has a given admin permission key.
     * Fallback to Spatie or AdminType flags.
     */
    public function hasAdminPermission(string $key)
    {
        // 1. Spatie Permission Check
        if ($this->hasPermissionTo($key)) return true;

        // 2. Legacy AdminType flag check
        if (!$this->adminType) return false;

        $flagMap = [
            'view_profits' => 'can_view_profits',
            'manage_admins' => 'can_manage_admins',
            'manage_finances' => 'can_manage_finances',
            'manage_financials' => 'can_manage_finances',
        ];

        $flag = $flagMap[$key] ?? null;
        return $flag ? (bool) $this->adminType->{$flag} : false;
    }

    /**
     * علاقة اليوزر مع مجموعات الانتظار (من خلال الطالب)
     */
    public function waitingGroups()
    {
        return $this->hasOneThrough(
            WaitingGroup::class,
            Student::class,
            'user_id', // Foreign key on students table
            'id', // Foreign key on waiting_groups table
            'id', // Local key on users table
            'student_id' // Local key on students table
        );
    }

    /**
     * علاقة اليوزر مع سجلات الانتظار (من خلال الطالب)
     */
    public function waitingStudentRecords()
    {
        return $this->hasOneThrough(
            WaitingStudent::class,
            Student::class,
            'user_id', // Foreign key on students table
            'student_id', // Foreign key on waiting_students table
            'id', // Local key on users table
            'student_id' // Local key on students table
        );
    }

    // في App\Models\User.php
    public function updateActiveStatus()
    {
        try {
            $student = $this->student;

            if (! $student) {
                // إذا لم يكن المستخدم مرتبطاً بطالب، اجعله غير نشط
                $this->is_active = false;
                $this->save();

                return;
            }

            // التحقق من المجموعات النشطة
            $inActiveGroup = $student->studentGroups()
                ->whereHas('group', function ($query) {
                    $query->where('status', 'active');
                })->exists();

            // التحقق من مجموعات الانتظار
            $inWaitingGroup = $student->waitingStudents()
                ->whereIn('status', ['waiting', 'contacted', 'approved'])
                ->exists();

            // تحديث حالة النشاط
            $shouldBeActive = $inActiveGroup || $inWaitingGroup;

            if ($this->is_active != $shouldBeActive) {
                $this->is_active = $shouldBeActive;
                $this->save();

                Log::info("User {$this->id} status updated to: ".($shouldBeActive ? 'active' : 'inactive'));
            }

            return $shouldBeActive;

        } catch (\Exception $e) {
            Log::error('Error in updateActiveStatus: '.$e->getMessage());

            return false;
        }
    }

    // في App\Models\User.php
    public function preferredCourse()
    {
        return $this->hasOneThrough(
            Course::class,
            Student::class,
            'user_id', // Foreign key on students table
            'course_id', // Foreign key on courses table
            'id', // Local key on users table
            'preferred_course_id' // Local key on students table
        );
    }

    public function adminVault()
    {
        return $this->hasOne(AdminVault::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(AdminWithdrawal::class);
    }
}
