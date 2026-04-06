<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Academic\CourseController;
use App\Http\Controllers\Finance\InvoicesController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Academic\SessionsController;
use App\Http\Controllers\Finance\FawryController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Authentication Routes
require __DIR__.'/auth.php';

// Public Routes
Route::get('/courses/explore', [CourseController::class, 'publicIndex'])->name('courses.explore');
Route::get('/courses/{id}/details', [CourseController::class, 'publicShow'])->name('courses.public.show');
Route::get('/invoices/view/{token}', [InvoicesController::class, 'publicShow'])->name('invoices.public.show');
Route::post('/invoices/{invoice}/payment', [InvoiceController::class, 'submitPayment'])->name('invoices.submit_payment')->middleware('auth');
Route::get('/invoices/{id}/share', [InvoicesController::class, 'share'])->name('invoices.share')->middleware('auth');

// Shareable Meeting Link — requires auth, then redirects to actual meeting
Route::get('/m/{meetingId}', [SessionsController::class, 'publicJoinMeeting'])->name('meeting.join.public')->middleware('auth');
Route::post('/sessions/meeting/{id}/toggle', [SessionsController::class, 'toggleMeetingStatus'])->name('session.meeting.toggle')->middleware('auth');

// WiFi Attendance Management for Teachers
Route::get('/sessions/{id}/attendance/wifi/status', [\App\Http\Controllers\Academic\SessionAttendanceController::class, 'getAttendanceWindowStatus'])->middleware('auth');
Route::post('/sessions/{id}/attendance/wifi/open', [\App\Http\Controllers\Academic\SessionAttendanceController::class, 'openAttendanceWindow'])->middleware('auth');
Route::post('/sessions/{id}/attendance/wifi/close', [\App\Http\Controllers\Academic\SessionAttendanceController::class, 'closeAttendanceWindow'])->middleware('auth');
Route::post('/sessions/{id}/attendance/qr/refresh', [\App\Http\Controllers\Academic\SessionAttendanceController::class, 'refreshQrToken'])->name('sessions.attendance.qr.refresh')->middleware('auth');

// Student Quick Check-in (WiFi)
Route::get('/s/{id}/check-in', [\App\Http\Controllers\Academic\SessionAttendanceController::class, 'showCheckInPage'])->name('session.checkin')->middleware('auth');
Route::post('/s/{id}/check-in', [\App\Http\Controllers\Academic\SessionAttendanceController::class, 'checkInViaWifi'])->middleware('auth');

// Fawry Payments
Route::get('/fawry/callback', [FawryController::class, 'callback'])->name('fawry.callback');
Route::post('/fawry/webhook', [FawryController::class, 'webhook'])->name('fawry.webhook');

// Authenticated Routes
Route::middleware(['auth'])->group(function () {

    // Admin & Management
    require __DIR__.'/admin.php';

    // Groups & Courses
    require __DIR__.'/groups.php';

    // Student Routes
    require __DIR__.'/student.php';

    // Teacher Routes
    require __DIR__.'/teacher.php';

    // Reports
    require __DIR__.'/reports.php';

    // Salaries & Invoices
    require __DIR__.'/salaries.php';

    
  

});
  require __DIR__.'/misc.php';