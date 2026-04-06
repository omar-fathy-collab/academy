<?php

use App\Http\Controllers\Finance\FinancialReportsController;
use App\Http\Controllers\Finance\MoneyReportsController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Student\StudentRegistrationReportController;
use App\Http\Controllers\Student\StudentReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin|super-admin'])->group(function () {
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('/financial', [FinancialReportsController::class, 'index'])->name('financial');

        // Financial Reports
        Route::prefix('financial')->name('financial.')->group(function () {
            Route::get('/', [FinancialReportsController::class, 'index'])->name('index');
            Route::get('/ajax', [ReportsController::class, 'getFinancialData'])->name('ajax');
            Route::get('/export-excel', [ReportsController::class, 'exportFinancialExcel'])->name('export.excel');
            Route::get('/new', [FinancialReportsController::class, 'index'])->name('new');
            Route::get('/export', [FinancialReportsController::class, 'exportExcel'])->name('export');
            Route::post('/data', [FinancialReportsController::class, 'getData'])->name('data');
        });
        Route::get('/financial-index', [FinancialReportsController::class, 'index'])->name('financial');

        // Student Reports
        Route::prefix('students')->name('students.')->group(function () {
            Route::get('/', [StudentReportController::class, 'index'])->name('index');
            Route::get('/daily', [StudentReportController::class, 'dailyReport'])->name('daily');
            Route::get('/weekly', [StudentReportController::class, 'weeklyReport'])->name('weekly');
            Route::get('/monthly', [StudentReportController::class, 'monthlyReport'])->name('monthly');
            Route::get('/annual', [StudentReportController::class, 'annualReport'])->name('annual');
            Route::get('/overall', [StudentReportController::class, 'overallReport'])->name('overall');
            Route::get('/overall/enhanced', [StudentReportController::class, 'enhancedOverallReport'])->name('overall.enhanced');

            // Legacy -report suffixes (frontend expects these)
            Route::get('/daily-report', [StudentReportController::class, 'dailyReport'])->name('daily.report');
            Route::get('/weekly-report', [StudentReportController::class, 'weeklyReport'])->name('weekly.report');
            Route::get('/monthly-report', [StudentReportController::class, 'monthlyReport'])->name('monthly.report');
            Route::get('/annual-report', [StudentReportController::class, 'annualReport'])->name('annual.report');
            Route::get('/overall-report', [StudentReportController::class, 'overallReport'])->name('overall.report');

            Route::get('/search', [StudentReportController::class, 'searchStudents'])->name('search');
            Route::get('/student/{studentId}', [StudentReportController::class, 'studentReport'])->name('student');
            Route::get('/export', [StudentReportController::class, 'exportReport'])->name('export');
            Route::get('/student/{studentId}/export', [StudentReportController::class, 'exportStudentReport'])->name('export.student');

            // Financial Sub-reports
            Route::prefix('financial')->name('financial.')->group(function () {
                Route::get('/complete-data', [StudentReportController::class, 'getCompleteFinancialData'])->name('complete');
                Route::get('/expired-groups-unpaid', [StudentReportController::class, 'getExpiredGroupsWithUnpaidInvoices'])->name('expired_groups_unpaid');
                Route::get('/about-to-expire-unpaid', [StudentReportController::class, 'getAboutToExpireGroupsWithUnpaid'])->name('about_to_expire_unpaid');
                Route::get('/paid-students-no-active-groups', [StudentReportController::class, 'getPaidStudentsWithoutActiveGroups'])->name('paid_students_no_active_groups');
                Route::get('/summary', [StudentReportController::class, 'getGroupsFinancialSummary'])->name('summary');
                Route::get('/details', [StudentReportController::class, 'getGroupsFinancialDetails'])->name('details');
                Route::get('/expired', [StudentReportController::class, 'getAllExpiredGroups'])->name('expired');
                Route::get('/about-to-expire', [StudentReportController::class, 'getAllAboutToExpire'])->name('about_to_expire');
                Route::get('/paid-students', [StudentReportController::class, 'getAllPaidStudents'])->name('paid_students');
            });

            // Legacy Financial Summary aliases
            Route::get('/groups-financial-summary', [StudentReportController::class, 'getGroupsFinancialSummary'])->name('financial.summary.legacy');
            Route::get('/expired-groups-with-unpaid-invoices', [StudentReportController::class, 'getExpiredGroupsWithUnpaidInvoices'])->name('financial.expired.legacy');
            Route::get('/about-to-expire-groups-with-unpaid', [StudentReportController::class, 'getAboutToExpireGroupsWithUnpaid'])->name('financial.about_to_expire.legacy');
            Route::get('/paid-students-without-active-groups', [StudentReportController::class, 'getPaidStudentsWithoutActiveGroups'])->name('financial.paid_students.legacy');
        });

        // Money Reports
        Route::prefix('money')->name('money.')->group(function () {
            Route::get('/', [MoneyReportsController::class, 'index'])->name('index');
            Route::get('/daily', [MoneyReportsController::class, 'dailyReport'])->name('daily');
            Route::get('/weekly', [MoneyReportsController::class, 'weeklyReport'])->name('weekly');
            Route::get('/monthly', [MoneyReportsController::class, 'monthlyReport'])->name('monthly');
            Route::get('/annual', [MoneyReportsController::class, 'annualReport'])->name('annual');
            Route::get('/overall', [MoneyReportsController::class, 'overallReport'])->name('overall');
            Route::get('/type/{type}', [MoneyReportsController::class, 'getReportByType'])->name('type');
            Route::get('/transaction/detail', [MoneyReportsController::class, 'transactionDetail'])->name('transaction.detail');
            Route::post('/transaction/status', [MoneyReportsController::class, 'changeTransactionStatus'])->name('transaction.status');
            Route::get('/export', [MoneyReportsController::class, 'exportExcel'])->name('export');
        });

        // Registration Reports
        Route::prefix('registrations')->name('registrations.')->group(function () {
            Route::get('/', [StudentRegistrationReportController::class, 'index'])->name('index');
            Route::get('/export', [StudentRegistrationReportController::class, 'exportExcel'])->name('export');
            Route::get('/statistics', [StudentRegistrationReportController::class, 'courseStatistics'])->name('statistics');
            Route::get('/{id}', [StudentRegistrationReportController::class, 'show'])->name('show');
            Route::post('/{id}/update-course', [StudentRegistrationReportController::class, 'updateCourseSelection'])->name('update-course');
        });

        Route::get('/registration-legacy', [StudentRegistrationReportController::class, 'index'])->name('registration');

        Route::get('/groups-financial/dashboard', [StudentReportController::class, 'getGroupsFinancialDashboard'])->name('groups_financial.dashboard');
        Route::get('/groups-financial/unpaid-details/{groupId}', [StudentReportController::class, 'getGroupUnpaidDetails'])->name('groups_financial.unpaid_details');
        Route::get('/groups-financial-status', [StudentReportController::class, 'getGroupsFinancialReport'])->name('groups_financial_status');

        Route::prefix('groups-financial')->name('groups-financial.')->group(function () {
            Route::get('/export', [StudentReportController::class, 'exportGroupsFinancialReport'])->name('export');
            Route::get('/alerts/send', [StudentReportController::class, 'sendGroupsFinancialAlerts'])->name('alerts.send');
            Route::get('/group/{groupId}/students', [StudentReportController::class, 'getGroupStudentsWithInvoices'])->name('group.students');
            Route::get('/summary', [StudentReportController::class, 'getGroupsFinancialSummary'])->name('summary');
        });

        // Interactive Reports
        Route::post('/groups/{groupId}/send-reminder', [StudentReportController::class, 'sendReminder'])->name('groups.send_reminder');
        Route::post('/students/{studentId}/promote', [StudentReportController::class, 'promoteCourse'])->name('students.promote');

        // Missing Report Routes for Sidebar/Index
        Route::get('/quizzes', [ReportsController::class, 'quizzes'])->name('quizzes');
        Route::get('/attendance', [ReportsController::class, 'attendance'])->name('attendance');
        Route::get('/performance', [ReportsController::class, 'performance'])->name('performance');
        Route::get('/groups-report', [ReportsController::class, 'groups'])->name('groups');
        Route::get('/certificates-report', [ReportsController::class, 'certificates'])->name('certificates');
        Route::get('/teachers-report', [ReportsController::class, 'teachers'])->name('teachers');
        Route::get('/revenue', [ReportsController::class, 'revenue'])->name('revenue');
        Route::get('/expenses-report', [ReportsController::class, 'expenses'])->name('expenses');
        Route::get('/profit', [ReportsController::class, 'profit'])->name('profit');
    });

    // API Reports (if needed as separate prefix)
    Route::prefix('api/students')->group(function () {
        Route::get('/groups-financial-summary', [StudentReportController::class, 'getGroupsFinancialSummary']);
        Route::get('/expired-groups-unpaid', [StudentReportController::class, 'getExpiredGroupsWithUnpaidInvoices']);
        Route::get('/about-to-expire-unpaid', [StudentReportController::class, 'getAboutToExpireGroupsWithUnpaid']);
        Route::get('/paid-students-no-active-groups', [StudentReportController::class, 'getPaidStudentsWithoutActiveGroups']);
        Route::get('/search', [StudentReportController::class, 'searchStudents'])->name('api.students.search');
        Route::get('/{student}/report', [StudentReportController::class, 'studentReport'])->name('api.students.report');
    });
});
