<?php

use App\Http\Controllers\Admin\AdminVaultController;
use App\Http\Controllers\Finance\CapitalController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Academic\RoomController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Import\UserImportController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Finance\TeacherSalariesController;
use App\Http\Controllers\Academic\TeachersController;
use App\Http\Controllers\Student\StudentInfoController;
use App\Http\Controllers\Academic\EnrollmentRequestController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Learning\QuizAttemptsController;
use App\Http\Controllers\Learning\QuizzesController;
use App\Http\Controllers\Finance\SalaryController;
use App\Http\Controllers\Academic\CourseController;
use App\Http\Controllers\Learning\AssignmentsController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Finance\MoneyReportsController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\InvoicesController;
use App\Http\Controllers\Learning\LibraryController;
use App\Http\Controllers\Learning\VideoProgressController;
use App\Http\Controllers\Learning\VideoController;
use App\Http\Controllers\Academic\GroupsController;
use App\Http\Controllers\Academic\StudentsController;
use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Learning\BookController;
use App\Http\Controllers\Academic\EnrollmentRequestController as EnrollmentRequest;
use App\Http\Controllers\Finance\FinancialController;

// Global Impersonation Escape Routes (Accessible by any authenticated user)
Route::post('/stop-impersonate', [UserController::class, 'stopImpersonate'])->name('impersonate.stop');
Route::post('/impersonate/stop', [UserController::class, 'stopImpersonate'])->name('impersonate.stop.alt');

Route::middleware(['auth', 'permission:manage_users'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin/vault', [AdminVaultController::class, 'index'])->name('admin.vault.index')->middleware('permission:view_vault');
    Route::post('/dashboard/add-capital', [DashboardController::class, 'addCapital'])->name('dashboard.addCapital');

    // Global Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index')->middleware('permission:manage_settings');
    Route::post('/settings', [SettingController::class, 'update'])
        ->middleware(\App\Http\Middleware\AdminVerificationMiddleware::class, 'permission:manage_settings')
        ->name('settings.update');

    // Roles & Permissions Management
    Route::resource('roles', RoleController::class)->middleware('permission:manage_roles');
    Route::get('/roles/{role}/users', [RoleController::class, 'users'])->name('roles.users')->middleware('permission:manage_roles');

    // Quiz Attempts
    Route::get('/quiz-attempts', [QuizAttemptsController::class, 'index'])->name('quiz.attempts.index');
    Route::get('/quiz-attempts/{attemptId}', [QuizAttemptsController::class, 'show'])->name('quiz.attempts.show');
    Route::delete('/quiz-attempts/{attemptId}', [QuizAttemptsController::class, 'destroy'])->name('quiz.attempts.destroy');
    Route::get('/students/{studentId}/attempts/{attemptId}', [QuizAttemptsController::class, 'showStudentAttempt'])->name('quiz.attempts.student.attempt');


    // Salary Transfers
    Route::get('/salaries/transfers', [SalaryController::class, 'showTransfers'])->name('salaries.transfers')->middleware('permission:view_financials');

    // Courses
    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/courses-legacy', [CourseController::class, 'index'])->name('courses');
    Route::get('/add_course', [CourseController::class, 'addForm'])->name('add_course')->middleware('permission:manage_courses');

    Route::get('/student_info', [StudentInfoController::class, 'show'])->name('student.info.show');

    // Teachers Management
    Route::group(['prefix' => 'teachers', 'as' => 'teachers.'], function () {
        Route::get('/', [TeachersController::class, 'index'])->name('index');
        Route::get('/fetch', [TeachersController::class, 'fetchTeachers'])->name('fetch');
        Route::get('/create', [TeachersController::class, 'create'])->name('create');
        Route::post('/', [TeachersController::class, 'store'])->name('store');
        Route::get('/{teacher}', [TeachersController::class, 'show'])->name('show');
        Route::get('/{teacher}/edit', [TeachersController::class, 'edit'])->name('edit');
        Route::put('/{teacher}', [TeachersController::class, 'update'])->name('update');
        Route::delete('/{teacher}', [TeachersController::class, 'destroy'])
            ->middleware(\App\Http\Middleware\AdminVerificationMiddleware::class, 'permission:manage_teachers')
            ->name('destroy');

        Route::get('/{teacher}/salary-management', [TeacherSalariesController::class, 'show'])->name('salary_management')->middleware('permission:view_financials');
        Route::get('/{teacher_id}/salaries', [TeacherSalariesController::class, 'show'])->name('salaries')->middleware('permission:view_financials');
        Route::get('/{teacher_id}/salaries/fetch', [TeacherSalariesController::class, 'getTeacherSalaryData'])->name('salaries.fetch')->middleware('permission:view_financials');
        Route::post('/{teacher}/create-salary-record', [TeacherSalariesController::class, 'createSalaryRecord'])->name('create_salary_record')->middleware('permission:manage_financials');
        Route::post('/{teacher}/create-missing-salary-records', [TeacherSalariesController::class, 'createMissingSalaryRecords'])->name('create_missing_salary_records')->middleware('permission:manage_financials');

        Route::group(['prefix' => '{teacher}/adjustments', 'as' => 'adjustments.'], function () {
            Route::put('/{adjustment}/mark-paid', [TeacherSalariesController::class, 'markAdjustmentPaid'])->name('mark_paid')->middleware('permission:manage_financials');
            Route::put('/{adjustment}/mark-unpaid', [TeacherSalariesController::class, 'markAdjustmentUnpaid'])->name('mark_unpaid')->middleware('permission:manage_financials');
        });

        Route::get('/stats/all', [TeachersController::class, 'getStats'])->name('stats');
    });

    // Assignments moved to inclusive group below


    // Security SOC Dashboard
    Route::group(['prefix' => 'security', 'as' => 'security.'], function () {
        Route::get('/', [\App\Http\Controllers\Admin\SecurityDashboardController::class, 'index'])->name('index')->middleware('permission:view_logs');
        Route::post('/unblock/{id}', [\App\Http\Controllers\Admin\SecurityDashboardController::class, 'unblock'])->name('unblock');
        Route::delete('/logs/clear', [\App\Http\Controllers\Admin\SecurityDashboardController::class, 'clearLogs'])->name('logs.clear');
        Route::post('/force-logout', [\App\Http\Controllers\Admin\SecurityDashboardController::class, 'forceLogoutAll'])->name('force-logout');
        Route::post('/mark-false-positive/{id}', [\App\Http\Controllers\Admin\SecurityDashboardController::class, 'markFalsePositive'])->name('mark-false-positive');
    });

    // Reports
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index')->middleware('permission:view_reports');
    Route::get('/reports/money', [MoneyReportsController::class, 'index'])->name('reports.money.index')->middleware('permission:view_financials');

    // Enrollment Requests moved to inclusive group below



});

// Routes accessible by both Admins and Instructors
Route::middleware(['auth'])->group(function () {
    // Assignments
    Route::resource('manage-assignments', AssignmentsController::class)->parameters(['manage-assignments' => 'assignment'])->names('assignments');
    Route::get('/manage-assignments/fetch/data', [AssignmentsController::class, 'fetchAssignments'])->name('assignments.fetch');
    Route::get('/grade_assignment', [AssignmentsController::class, 'gradeAssignment'])->name('assignments.grade_assignment');
    Route::post('/grade_submission', [AssignmentsController::class, 'gradeSubmission'])->name('assignments.grade_submission');

    // Enrollment Requests
    Route::post('enrollment-requests/manual-enroll', [EnrollmentRequestController::class, 'manualEnroll'])->name('enrollment-requests.manual-enroll');
    Route::resource('enrollment-requests', EnrollmentRequestController::class)->only(['index', 'update', 'destroy']);
    
    // Quiz Management (Shared)
    Route::get('/quizzes/fetch', [QuizzesController::class, 'fetch'])->name('quizzes.fetch');
    Route::post('/quizzes/clone', [QuizzesController::class, 'clone'])->name('quizzes.clone');

    // Group-specific enrollment requests (from GroupsController)
    Route::get('/groups/enrollment-requests', [GroupsController::class, 'enrollmentRequests'])->name('admin.groups.enrollment_requests');
    Route::post('/groups/enrollment-requests/{id}/update-status', [GroupsController::class, 'updateEnrollmentStatus'])->name('admin.groups.update_enrollment_status');
});



// Financial Management (Accessible by users with view_financials or view_vault)
Route::middleware(['auth'])->group(function () {
    // Expenses
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index')->middleware('permission:view_financials');
    Route::get('/expenses/add', [ExpenseController::class, 'add'])->name('expenses.add')->middleware('permission:manage_financials');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store')->middleware('permission:manage_financials');
    Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve')->middleware('permission:manage_financials');
    Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject')->middleware('permission:manage_financials');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy')->middleware('permission:manage_financials');
    Route::get('/expenses/export', [ExpenseController::class, 'export'])->name('expenses.export')->middleware('permission:view_financials');

    // Invoices
    Route::get('/invoices', [InvoicesController::class, 'index'])->name('invoices.index')->middleware('permission:view_financials');
    Route::get('/invoices/fetch', [InvoicesController::class, 'fetchInvoices'])->name('invoices.fetch')->middleware('permission:view_financials');
    Route::get('/invoices/export', [InvoicesController::class, 'export'])->name('invoices.export')->middleware('permission:view_financials');
    Route::post('/invoices', [InvoicesController::class, 'store'])->name('invoices.store')->middleware('permission:manage_financials');
    Route::get('/invoices/{invoice}', [InvoicesController::class, 'show'])->name('invoices.show')->middleware('permission:view_financials');
    Route::get('/invoices/{invoice}/edit', [InvoicesController::class, 'edit'])->name('invoices.edit')->middleware('permission:manage_financials');
    Route::put('/invoices/{invoice}', [InvoicesController::class, 'update'])->name('invoices.update')->middleware('permission:manage_financials');
    Route::delete('/invoices/{invoice}', [InvoicesController::class, 'destroy'])->name('invoices.destroy')->middleware('permission:manage_financials');
    Route::post('/invoices/mark-paid', [InvoicesController::class, 'markAllAsPaid'])->name('invoices.mark_paid')->middleware('permission:manage_financials');
    Route::post('/invoices/{invoice}/resend-whatsapp', [InvoicesController::class, 'resendWhatsApp'])->name('invoices.resend_whatsapp')->middleware('permission:view_financials');
    Route::post('/invoices/{invoice}/resend-email', [InvoicesController::class, 'resendEmail'])->name('invoices.resend_email')->middleware('permission:view_financials');

    // Unified Financial Transactions
    Route::post('/financial/record-transaction', [FinancialController::class, 'recordTransaction'])->name('financial.record_transaction')->middleware('permission:manage_financials');

    // Vault Management
    Route::group(['prefix' => 'admin/vault', 'as' => 'admin.vault.'], function () {
        Route::get('/', [AdminVaultController::class, 'index'])->name('index')->middleware('permission:view_vault');
        Route::post('/', [AdminVaultController::class, 'store'])->name('store')->middleware('permission:manage_vault');
        Route::post('/distribute', [AdminVaultController::class, 'distribute'])->name('distribute')->middleware('permission:manage_vault');
        Route::post('/distribute-profit', [AdminVaultController::class, 'distributeProfit'])->name('distribute_profit')->middleware('permission:manage_vault');
        Route::get('/stats', [AdminVaultController::class, 'getVaultStats'])->name('stats')->middleware('permission:view_vault');
        Route::get('/my-withdrawals', [AdminVaultController::class, 'myWithdrawals'])->name('my-withdrawals');
        Route::post('/withdraw', [AdminVaultController::class, 'requestWithdrawal'])->name('withdraw');
        Route::post('/withdrawals/{id}/approve', [AdminVaultController::class, 'approveWithdrawal'])->name('approve_withdrawal')->middleware('permission:manage_vault');
        Route::post('/withdrawals/{id}/reject', [AdminVaultController::class, 'rejectWithdrawal'])->name('reject_withdrawal')->middleware('permission:manage_vault');
        Route::post('/withdrawals/{id}/complete', [AdminVaultController::class, 'completeWithdrawal'])->name('complete_withdrawal')->middleware('permission:manage_vault');
        Route::post('/withdrawals/{id}/cancel', [AdminVaultController::class, 'cancelCompletedWithdrawal'])->name('cancel_withdrawal')->middleware('permission:manage_vault');
        Route::get('/withdrawals/{id}', [AdminVaultController::class, 'withdrawalDetails'])->name('withdrawal_details');
        Route::delete('/withdrawals/{id}', [AdminVaultController::class, 'deleteWithdrawal'])->name('delete_withdrawal');
        Route::put('/withdrawals/{id}', [AdminVaultController::class, 'updateWithdrawal'])->name('update_withdrawal');
        Route::post('/add-capital', [AdminVaultController::class, 'addCapitalToVault'])->name('add_capital')->middleware('permission:manage_vault');
    });

    // Reports Money
    Route::get('/reports/money', [MoneyReportsController::class, 'index'])->name('reports.money.index')->middleware('permission:view_financials');

    // Academy Library
    Route::get('/admin-library', [LibraryController::class, 'index'])->name('admin.library');
    Route::post('/library/video/store', [LibraryController::class, 'storeVideo'])->name('admin.library.video.store');
    Route::post('/library/book/store', [LibraryController::class, 'storeBook'])->name('admin.library.book.store');
    Route::put('/library/{type}/{id}/update', [LibraryController::class, 'updateAsset'])->name('admin.library.update');
    Route::post('/library/{type}/{id}/toggle', [LibraryController::class, 'toggleVisibility'])->name('admin.library.toggle');
    Route::post('/library/{type}/{id}/delete', [LibraryController::class, 'deleteAsset'])->name('admin.library.delete');
    Route::post('/library/{id}/add', [LibraryController::class, 'addToLibrary'])->name('admin.library.add');
    
    // Library Payments
    Route::get('/library/payments', [LibraryController::class, 'pendingPayments'])->name('admin.library.payments');
    Route::post('/library/payments/{id}/update', [LibraryController::class, 'updatePaymentStatus'])->name('admin.library.payments.update');
    
    // Video Monitoring
    Route::get('/videos/engagement', [VideoProgressController::class, 'showEngagement'])->name('videos.engagement')->middleware('role:teacher|admin|super-admin');
    Route::get('/videos/{id}/show', [VideoController::class, 'showDetails'])->name('videos.details')->middleware('role:teacher|admin|super-admin');
    Route::get('/videos/{id}/data', [VideoProgressController::class, 'getEngagement'])->name('videos.details.data')->middleware('role:teacher|admin|super-admin');
});

Route::middleware(['auth', 'permission:manage_users'])->group(function () {
    // User Management Extended
    Route::get('/users/import', [UserImportController::class, 'showImportForm'])->name('users.import');
    Route::post('/users/import', [UserImportController::class, 'processImport'])->name('users.import.process');
    Route::get('/users/stats', [UserController::class, 'getStats'])->name('users.stats');
    Route::get('/users/fetch/data', [UserController::class, 'fetchData'])->name('users.fetch.data');
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonateUser'])->name('users.impersonate');
    Route::resource('users', UserController::class);
    Route::get('/users/{user}/details', [UserController::class, 'getUserDetails'])->name('users.details');
    Route::get('/pending-students', [UserController::class, 'pendingStudents'])->name('pending.students');

    // Room Management
    Route::resource('rooms', RoomController::class);
    Route::post('/rooms/{room}/toggle-status', [RoomController::class, 'toggleStatus'])->name('rooms.toggle-status');

    // Groups
    // Route::get('/groups', [GroupsController::class, 'index'])->name('groups.index');

    // Group enrollment requests moved to inclusive group above


    // Students Management
    Route::group(['prefix' => 'students', 'as' => 'students.'], function () {
        Route::get('/', [StudentsController::class, 'index'])->name('index');
        Route::get('/fetch', [StudentsController::class, 'fetch'])->name('fetch');
        Route::get('/export', [StudentsController::class, 'export'])->name('export');
        Route::post('/update', [StudentsController::class, 'update'])->name('update');
        Route::delete('/delete', [StudentsController::class, 'delete'])->name('delete');
        Route::get('/search', [StudentsController::class, 'search'])->name('search');
        Route::post('/add-to-waiting-group', [StudentsController::class, 'addStudentToWaitingGroup'])->name('waiting-group.add');
    });

    // Additional Helpers
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::post('/activities/{id}/rollback', [ActivityController::class, 'rollback'])->name('activities.rollback');

    // Books
    Route::post('/sessions/{session}/books', [BookController::class, 'store'])->name('sessions.books.store');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
});
