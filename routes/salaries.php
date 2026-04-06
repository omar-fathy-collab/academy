<?php

use App\Http\Controllers\Finance\InvoicesController;
use App\Http\Controllers\Finance\SalaryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:view_financials'])->group(function () {
    Route::resource('salaries', SalaryController::class);
    Route::post('/salaries/search', [SalaryController::class, 'search'])->name('salaries.search');
    Route::get('/salaries/calculate', [SalaryController::class, 'calculate'])->name('salaries.calculate');
    Route::post('/salaries/calculate', [SalaryController::class, 'calculateProcess'])->name('salaries.calculate.process');
    Route::get('/salaries/{salary_id}/pay', [SalaryController::class, 'pay'])->name('salaries.pay');
    Route::post('/salaries/{salary_id}/pay', [SalaryController::class, 'processPayment'])->name('salaries.process-payment');

    // AJAX Helpers
    Route::get('/salaries/get-group-details', [SalaryController::class, 'getGroupDetails'])->name('salaries.getGroupDetails');
    Route::get('/salaries/get-group-details-for-salary', [SalaryController::class, 'getGroupDetailsForSalary'])->name('salaries.getGroupDetailsForSalary');
    Route::get('/salaries/check-existing', [SalaryController::class, 'checkExisting'])->name('salaries.checkExisting');
    Route::get('/salaries/get-groups-by-teacher', [SalaryController::class, 'getGroupsByTeacher'])->name('salaries.getGroupsByTeacher');

    // Diagnostics & Fixes
    Route::get('/salaries/{salary}/verify-payments', [SalaryController::class, 'verifyPayments'])->name('salaries.verify-payments');
    Route::post('/salaries/{salary}/fix-payments', [SalaryController::class, 'fixPayments'])->name('salaries.fix-payments');
    Route::get('/salaries/{salary_id}/diagnose', [SalaryController::class, 'diagnosePaymentIssue'])->name('salaries.diagnose');
    Route::get('/salaries/{salary_id}/debug', [SalaryController::class, 'debugSalary'])->name('salaries.debug');


    Route::get('/invoices/get-students-by-group', [InvoicesController::class, 'getStudentsByGroup'])->name('invoices.getStudentsByGroup');

    Route::post('/salaries/{salary_id}/notify', [SalaryController::class, 'notify'])->name('salaries.notify');

    Route::group(['prefix' => 'api'], function () {
        Route::get('/invoices/students-by-group', [InvoicesController::class, 'getStudentsByGroup']);
    });

    // Salary Transfers (Additional routes not covered by resource)
    Route::post('/salaries/{salary}/transfer', [SalaryController::class, 'transferSalary'])->name('salaries.transfer');
    Route::get('/salaries/{salary}/transfer-teachers', [SalaryController::class, 'getTeachersForTransfer'])->name('salaries.transfer-teachers');
    Route::post('/salaries/transfer/{transfer}/confirm', [SalaryController::class, 'confirmTransferPayment'])->name('salaries.transfer.confirm');
    Route::delete('/salaries/transfer/{transfer}', [SalaryController::class, 'destroyTransfer'])->name('salaries.transfer.destroy');
});

// Public Salary Slip (No auth required if using public token)
Route::get('salary-slip/{token}', [SalaryController::class, 'publicSlip'])->name('salaries.public_slip');
