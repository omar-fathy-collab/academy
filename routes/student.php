<?php

use App\Http\Controllers\Learning\AssignmentsController;
use App\Http\Controllers\Academic\SessionAttendanceController;
use App\Http\Controllers\Student\StudentAssignmentController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\StudentInvoiceViewController;
use App\Http\Controllers\Student\StudentMaterialsController;
use App\Http\Controllers\Student\StudentPaymentController;
use App\Http\Controllers\Student\StudentQuizController;
use App\Http\Controllers\Learning\VideoProgressController;
use App\Http\Controllers\Academic\GroupsController;
use App\Http\Controllers\Academic\SessionsController;
use App\Http\Controllers\Learning\LibraryController;
use App\Http\Controllers\Learning\VideoController;
use App\Http\Controllers\Student\StudentInvoicesController;
use App\Http\Controllers\Learning\BookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:web', 'role:student|admin|super-admin'])->group(function () {
    Route::get('/student-dashboard', [StudentDashboardController::class, 'dashboard'])->name('student.dashboard.index');
    Route::get('/student_dashboard', [StudentDashboardController::class, 'dashboard'])->name('student.dashboard');
    Route::get('/student/materials', [StudentMaterialsController::class, 'index'])->name('student.materials.index');
    Route::get('/student/sessions/{session_id}/materials', [StudentMaterialsController::class, 'sessionMaterials'])->name('student.sessions.materials');
    Route::get('/student/invoices', [StudentInvoiceViewController::class, 'index'])->name('student.invoices');
    Route::get('/student/quizzes', [StudentQuizController::class, 'myQuizzes'])->name('student.quizzes');
    Route::get('/student/assignments', [StudentAssignmentController::class, 'index'])->name('student.assignments');

    // Groups & Sessions
    Route::get('/my_groups', [StudentDashboardController::class, 'myGroups'])->name('student.my_groups');
    Route::get('/my_groups/{group}', [StudentDashboardController::class, 'groupDetails'])->name('student.group_details');
    Route::get('/my_requests', [GroupsController::class, 'myEnrollmentRequests'])->name('student.groups.requests');
    Route::post('/groups/{id}/request-join', [GroupsController::class, 'requestJoin'])->name('student.groups.request_join');
    Route::get('/my_sessions', [StudentDashboardController::class, 'mySessions'])->name('student.my_sessions');
    Route::get('/my_certificates', [StudentDashboardController::class, 'certificates'])->name('student.certificates.index');
    Route::post('/student/certificate-request', [StudentDashboardController::class, 'requestCertificate'])->name('student.certificates.request');
    Route::get('/my_sessions/{session_id}', [StudentDashboardController::class, 'sessionDetails'])->name('student.session_details');
    Route::get('/student/sessions/{session}/join', [SessionsController::class, 'joinMeeting'])->name('student.sessions.join');
    Route::post('/student/sessions/{session}/log-event', [SessionsController::class, 'logMeetingEvent'])->name('student.sessions.log_event');
    Route::post('/student/sessions/{session}/log-leave', [SessionsController::class, 'logMeetingLeave'])->name('student.sessions.log_leave');
    Route::get('/student/session/{session_id}/material/{file_name}/download', [StudentDashboardController::class, 'downloadSessionMaterial'])->name('student.session.material.download')
        ->where('file_name', '.*');

    Route::get('/library', [LibraryController::class, 'index'])->name('student.library');
    Route::post('/library/request-access', [LibraryController::class, 'submitPaymentRequest'])->name('student.library.request_access');
});

// Shared between Students and Teachers
Route::middleware(['auth:web', 'role:student|teacher|admin|super-admin'])->group(function () {
    Route::get('/api/secure-video/{video_id}/url', [VideoController::class, 'getSignedUrl'])->name('student.secure_video.url');

    
    Route::get('/api/student/video-progress/{material_id}', [VideoProgressController::class, 'getProgress'])->name('student.video-progress.get');
});

Route::middleware(['auth:web', 'role:student|admin|super-admin'])->group(function () {


    // Proximity Session Check-In (WiFi)
    Route::post('/api/student/sessions/{session}/checkin', [SessionAttendanceController::class, 'checkInViaWifi'])->name('api.student.attendance.checkin');
    Route::post('/api/student/attendance/mark-present/{session}', [SessionAttendanceController::class, 'markRemoteAttendance'])->name('api.student.attendance.mark_present');

    Route::get('/my-assignments', [StudentAssignmentController::class, 'index'])->name('student.my_assignments');
    Route::get('/assignments/{id}/submit', [StudentDashboardController::class, 'submitAssignment'])->name('student.submit_assignment');
    Route::post('/assignments/{assignment}/submit', [StudentAssignmentController::class, 'processSubmission'])->name('student.submit_assignment.post');

    Route::get('/take-quiz/{quiz}', [StudentQuizController::class, 'takeQuiz'])->name('student.take_quiz');
    Route::post('/take-quiz/{quiz}', [StudentQuizController::class, 'takeQuiz'])->name('student.take_quiz.post');
    Route::get('/student_quiz_results', [StudentQuizController::class, 'quizResults'])->name('student.quiz.results');
    Route::get('/my_quizzes', [StudentQuizController::class, 'myQuizzes'])->name('student.my_quizzes');

    Route::get('/student/payments', [StudentPaymentController::class, 'index'])->name('student.payments');
    Route::get('/student/payments/history', [StudentPaymentController::class, 'history'])->name('student.payments.history');
    Route::get('/student/payments/{invoice_id}/pay', [StudentPaymentController::class, 'show'])->name('student_payments.show');
    Route::post('/student/payments/process', [StudentPaymentController::class, 'processPayment'])->name('student_payments.process');
    // Aliases for Consistency with all components
    Route::get('/student/payments/{invoice_id}/pay-alias', [StudentPaymentController::class, 'show'])->name('student.payment.show');
    Route::post('/student/payments/process-alias', [StudentPaymentController::class, 'processPayment'])->name('student.payment.process');
    Route::get('/student/payments/{invoice_id}/whatsapp-url', [StudentPaymentController::class, 'getWhatsAppUrl'])->name('student.payment.whatsapp_url');
    Route::post('/student/payments/{invoice_id}/send-email', [StudentPaymentController::class, 'sendManualEmail'])->name('student.payment.send_email');

    Route::get('/view_invoice/{id}', [StudentInvoiceViewController::class, 'show'])->name('student.invoice.view');
    Route::get('/student_invoices', [StudentInvoicesController::class, 'studentInvoices'])->name('student.invoices.view');
    // Alias for student_invoices.view
    Route::get('/student_invoices_alias', [StudentInvoicesController::class, 'studentInvoices'])->name('student_invoices.view');

    // Books
    Route::get('/student/books/{id}/view', [BookController::class, 'view'])->name('student.books.view');
});

// Publicly accessible via Signed URL (Identity Fallback)
// Secure video streaming and key (always requires signature)
Route::middleware(['signed'])->group(function() {
    Route::get('/api/secure-video/{video_id}', [VideoController::class, 'stream'])->name('student.secure_video.stream');
    Route::get('/api/secure-video/{video_id}/key', [VideoController::class, 'getKey'])->name('student.secure_video.key');
    Route::get('/api/secure-book/{id}', [BookController::class, 'stream'])->name('student.books.stream');
});

// Heartbeat tracking (optional signature or session)
Route::post('/api/student/video-progress/{material_id}/heartbeat', [VideoProgressController::class, 'heartbeat'])->name('student.video-progress.heartbeat');

