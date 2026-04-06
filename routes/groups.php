<?php

use App\Http\Controllers\Academic\BookingController;
use App\Http\Controllers\Academic\CourseController;
use App\Http\Controllers\Import\CourseImportController;
use App\Http\Controllers\Import\GroupImportController;
use App\Http\Controllers\Academic\GroupsController;
use App\Http\Controllers\Finance\InvoicesController;
use App\Http\Controllers\Learning\QuizzesController;
use App\Http\Controllers\Academic\SchedulesController;
use App\Http\Controllers\Learning\SessionMaterialsController;
use App\Http\Controllers\Academic\SessionsController;
use App\Http\Controllers\Academic\SubcourseController;
use App\Http\Controllers\Learning\VideoController;
use App\Http\Controllers\Academic\WaitingGroupController;
use App\Http\Controllers\Learning\VideoProgressController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:student|teacher|admin|super-admin'])->group(function () {
    // Group Management
    Route::get('/groups/fetch/data', [GroupsController::class, 'fetchData'])->name('groups.fetch.data');
    Route::post('/groups/check-schedule-availability', [GroupsController::class, 'checkScheduleAvailability'])->name('groups.check-schedule-availability');
    Route::resource('groups', GroupsController::class);
    Route::post('/groups/{group}/sessions', [GroupsController::class, 'addSession'])->name('groups.sessions.store');
    Route::get('/groups/get-subcourses/{courseId}', [GroupsController::class, 'getSubcoursesByCourse'])->name('groups.getSubcourses');
    Route::get('/groups/get-groups-by-teacher/{teacherId}', [GroupsController::class, 'getGroupsByTeacher'])->name('groups.getGroupsByTeacher');
    Route::post('/groups/create-salary-for-group', [GroupsController::class, 'createSalaryForGroup'])->name('groups.createSalaryForGroup');
    Route::get('/groups/get-salary-count/{groupId}', [GroupsController::class, 'getSalaryCount'])->name('groups.getSalaryCount');

    // Course Management
    Route::get('/ajax/fetch-courses', [CourseController::class, 'fetchCourses'])->name('courses.fetch.ajax');
    Route::get('/courses/fetch', [CourseController::class, 'fetchCourses'])->name('courses.fetch.legacy');
    Route::resource('courses', CourseController::class);
    Route::post('/courses/{id}/enroll', [CourseController::class, 'enroll'])->name('courses.enroll');
    Route::get('/courses/{courseId}/subcourses', [CourseController::class, 'showSubcourses'])->name('subcourses');
    Route::delete('/courses/{id}', [CourseController::class, 'destroy'])->name('delete_course');
    Route::get('/api/courses/{courseId}/subcourses', [BookingController::class, 'getSubcoursesByCourse']);

    // Subcourse Management
    Route::get('/subcourses/add', [SubcourseController::class, 'create'])->name('subcourses.create');
    Route::resource('subcourses', SubcourseController::class)->except(['create']);
    Route::get('/subcourses/{subcourse}/edit', [SubcourseController::class, 'edit'])->name('subcourses.edit');
    Route::delete('/subcourses/{id}', [SubcourseController::class, 'destroy'])->name('delete_subcourse');

    // Waiting Groups (Fixing 'Whitening' typo)
    Route::resource('waiting-groups', WaitingGroupController::class);
    Route::get('/waiting-groups/get-available-students', [WaitingGroupController::class, 'getAvailableStudents'])->name('waiting-groups.get-available-students');
    Route::get('/waiting-groups/get-students-by-course/{courseId}', [WaitingGroupController::class, 'getStudentsByCourse'])->name('waiting-groups.get-students-by-course');
    Route::get('/api/waiting-groups', [WaitingGroupController::class, 'getWaitingGroups'])->name('api.waiting-groups');
    Route::get('/api/waiting-groups/{groupName}/info', function ($groupName) {
        try {
            $groupInfo = \App\Models\WaitingGroup::where('group_name', $groupName)
                ->selectRaw('group_name, COUNT(*) as student_count, MAX(created_at) as created_at, MAX(course_id) as course_id')
                ->groupBy('group_name')
                ->first();
            if (! $groupInfo) {
                return response()->json(['success' => false, 'message' => 'المجموعة غير موجودة']);
            }
            $course = \App\Models\Course::find($groupInfo->course_id);

            return response()->json([
                'success' => true,
                'group_name' => $groupInfo->group_name,
                'student_count' => $groupInfo->student_count,
                'course_name' => $course ? $course->course_name : null,
                'created_at' => \Carbon\Carbon::parse($groupInfo->created_at)->format('d/m/Y'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'حدث خطأ في جلب المعلومات'], 500);
        }
    })->name('waiting-groups.info');
    Route::get('/api/waiting-groups/{groupName}/students', function ($groupName) {
        try {
            $students = \App\Models\WaitingGroup::with('booking')
                ->where('group_name', $groupName)
                ->get()
                ->map(function ($wg) {
                    return [
                        'id' => $wg->booking_id,
                        'name' => $wg->booking ? $wg->booking->name : 'غير معروف',
                    ];
                });

            return response()->json([
                'success' => true,
                'group_name' => $groupName,
                'students' => $students,
                'count' => count($students),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'حدث خطأ في جلب الطلاب'], 500);
        }
    })->name('waiting-groups.students-list');

    Route::get('/waiting-groups/{id}', [WaitingGroupController::class, 'show'])->name('waiting-groups.show');
    Route::put('/waiting-groups/{id}/transfer', [BookingController::class, 'transferWaitingGroup'])->name('waiting-groups.transfer');
    Route::get('/waiting-groups/add-student', [BookingController::class, 'addStudentToWaitingGroupForm'])->name('waiting-groups.add-student-form');
    Route::post('/waiting-groups/add-student', [BookingController::class, 'addNewStudentToWaitingGroup'])->name('waiting-groups.add-student');
    Route::post('/waiting-groups/{studentId}/move-to-group', [BookingController::class, 'moveStudentToGroup'])->name('waiting-groups.move-to-group');
    Route::post('/waiting-groups/{studentId}/edit', [BookingController::class, 'editWaitingStudent'])->name('waiting-groups.edit-student');
    Route::delete('/waiting-groups/{studentId}/remove', [BookingController::class, 'removeStudentFromGroup'])->name('waiting-groups.remove-student');
    Route::delete('/waiting-groups/{id}', [BookingController::class, 'removeFromWaitingGroup'])->name('waiting-groups.destroy');

    // Bookings
    Route::resource('bookings', BookingController::class);
    Route::get('/bookings/search', [BookingController::class, 'search'])->name('bookings.search');
    Route::get('/bookings/{booking}/add-to-waiting-group', [BookingController::class, 'showWaitingGroupForm'])->name('bookings.waiting-group');
    Route::post('/bookings/{booking}/add-to-waiting-group', [BookingController::class, 'addToWaitingGroup'])->name('bookings.add-to-waiting-group');
    Route::get('/bookings/{booking}/transfer-group/{group}', [BookingController::class, 'showTransferForm'])->name('bookings.transfer-group-form');
    Route::post('/bookings/{booking}/transfer-group', [BookingController::class, 'transferToWaitingGroup'])->name('bookings.transfer-waiting-group');
    Route::post('/bookings/{id}/update-grade', [BookingController::class, 'updatePlacementGrade'])->name('bookings.update-grade');
    Route::get('/bookings/filter/in-groups', [BookingController::class, 'filterInGroups'])->name('bookings.filter.in-groups');
    Route::get('/bookings/filter/not-in-groups', [BookingController::class, 'filterNotInGroups'])->name('bookings.filter.not-in-groups');
    Route::get('/invoices/get-students-by-group', [InvoicesController::class, 'getStudentsByGroup'])->name('invoices.getStudentsByGroup');

    // Schedules
    Route::get('/schedules/weekly', [SchedulesController::class, 'weeklyCalendar'])->name('schedules.weekly');
    Route::get('/schedules/monthly', [SchedulesController::class, 'monthlyCalendar'])->name('schedules.monthly');
    Route::get('/schedules/annual', [SchedulesController::class, 'annualCalendar'])->name('schedules.annual');
    Route::resource('schedules', SchedulesController::class);
    Route::get('/schedules/weekly/print', [SchedulesController::class, 'printWeekly'])->name('schedules.print.weekly');
    Route::get('/schedules/monthly/print', [SchedulesController::class, 'printMonthly'])->name('schedules.print.monthly');

    // Session Materials
    Route::get('/sessions/materials', [SessionMaterialsController::class, 'index'])->name('sessions.materials.index');

    // Sessions
    Route::resource('sessions', SessionsController::class);

    Route::post('/sessions/{id}/materials', [SessionMaterialsController::class, 'store'])->name('sessions.materials.store');
    Route::get('/sessions/materials/{id}/download', [SessionMaterialsController::class, 'download'])->name('sessions.materials.download');
    Route::get('/sessions/materials/{id}/preview', [SessionMaterialsController::class, 'preview'])->name('sessions.materials.preview');
    Route::delete('/sessions/materials/{id}', [SessionMaterialsController::class, 'destroy'])->name('sessions.materials.destroy');

    // Video Management
    Route::post('/sessions/{id}/videos', [VideoController::class, 'store'])->name('sessions.videos.store');
    Route::delete('/videos/{id}', [VideoController::class, 'destroy'])->name('videos.destroy');
    Route::get('/videos/{id}/engagement', [VideoProgressController::class, 'showEngagement'])->name('videos.engagement');
    Route::get('/api/videos/{id}/engagement-data', [VideoProgressController::class, 'getEngagement'])->name('videos.engagement.data');

    // Quizzes
    Route::get('/quizzes', [QuizzesController::class, 'index'])->name('quizzes.index');
    Route::resource('quizzes', QuizzesController::class)->only(['create', 'store', 'show', 'edit', 'update', 'destroy']);

    Route::post('/quizzes/{quiz_id}/questions', [QuizzesController::class, 'storeQuestion'])->name('quizzes.questions.store');
    Route::post('/quizzes/{quiz_id}/questions/bulk', [QuizzesController::class, 'bulkStore'])->name('quizzes.questions.bulk');
    Route::post('/quizzes/{quiz_id}/questions/{question_id}', [QuizzesController::class, 'updateQuestion'])->name('quizzes.questions.update');
    Route::delete('/quizzes/{quiz_id}/questions/{question_id}', [QuizzesController::class, 'destroyQuestion'])->name('quizzes.questions.destroy');
    Route::get('/quizzes/{quizId}/attempts', [QuizzesController::class, 'showAttempts'])->name('quizzes.attempts');

    // Import
    Route::post('/groups/import', [GroupImportController::class, 'import'])->name('groups.import');
    Route::post('/courses/import', [CourseImportController::class, 'import'])->name('courses.import');
});

