<?php

use App\Http\Controllers\Academic\CertificateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Learning\RatingController;
use App\Http\Controllers\Academic\SessionAttendanceController;
use App\Http\Controllers\Academic\TeachersController;
use App\Http\Controllers\Learning\AssignmentsController;
use App\Http\Controllers\Academic\SessionsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:teacher|admin|super-admin'])->group(function () {
    Route::get('/teacher-dashboard', [TeachersController::class, 'dashboard'])->name('teacher.dashboard');

    // Assignments Grading
    Route::get('/view_submissions', [AssignmentsController::class, 'index'])->name('assignments.view_submissions');
    Route::get('/assignments/{assignment}/submissions', [AssignmentsController::class, 'show'])->name('assignments.submissions');
    Route::get('/grade_assignment', [AssignmentsController::class, 'gradeAssignment'])->name('assignments.grade_assignment');
    Route::post('/grade_submission', [AssignmentsController::class, 'gradeSubmission'])->name('assignments.grade_submission');

    // Rating routes
    Route::get('/monthly_rating', [RatingController::class, 'monthly'])->name('ratings.monthly');
    Route::post('/save_monthly_ratings', [RatingController::class, 'saveMonthlyRatings'])->name('ratings.save_monthly');

    // Teacher Certificates
    Route::get('/teacher/certificates', [CertificateController::class, 'teacherIndex'])->name('teacher.certificates.index');
    Route::get('/teacher/certificates/create', [CertificateController::class, 'teacherCreate'])->name('teacher.certificates.create');
    Route::post('/teacher/certificates/store', [CertificateController::class, 'teacherStore'])->name('teacher.certificates.store');

    // Session Proximity Attendance (WiFi Window)
    Route::post('/api/sessions/{session}/attendance/open', [SessionAttendanceController::class, 'openAttendanceWindow'])->name('api.teacher.session.attendance.open');
    Route::post('/api/sessions/{session}/attendance/close', [SessionAttendanceController::class, 'closeAttendanceWindow'])->name('api.teacher.session.attendance.close');
    Route::get('/api/sessions/{session}/attendance/status', [SessionAttendanceController::class, 'getAttendanceWindowStatus'])->name('api.teacher.session.attendance.status');

    // Meeting click-through analytics
    Route::get('/teacher/sessions/{session}/meeting-log', [SessionsController::class, 'meetingLog'])->name('teacher.session.meeting_log');
});
