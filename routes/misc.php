<?php

use App\Http\Controllers\Academic\CertificateController;
use App\Http\Controllers\Academic\CertificateRequestController;
use App\Http\Controllers\Academic\CertificateTemplateController;
use App\Http\Controllers\Common\SearchController;
use App\Http\Controllers\Common\ProfileController;
use App\Http\Controllers\Admin\ActivityController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    // Global Search
    Route::get('/global-search', [SearchController::class, 'globalSearch'])->name('search.global');

    // Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');

    // Notifications
    Route::post('/mark-notifications-read', function () {
        try {
            $userId = Auth::id();
            if (! $userId) {
                return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
            }
            $updated = DB::table('notifications')
                ->where('user_id', $userId)
                ->update(['is_read' => 1]);

            return response()->json([
                'success' => true,
                'message' => 'Notifications marked as read',
                'updated_count' => $updated,
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking notifications read: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    })->name('notifications.markRead');

    // Legacy notification route (unified to same logic)
    Route::post('/mark_notifications_read', function () {
        try {
            $userId = Auth::id();
            if ($userId) {
                DB::table('notifications')
                    ->where('user_id', $userId)
                    ->update(['is_read' => 1]);
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false], 500);
        }
    })->name('notifications.markRead.legacy');

    // Activities
    Route::get('/activities/search', [ActivityController::class, 'search'])->name('activities.search');
    Route::get('/activities/{id}/details', [ActivityController::class, 'details'])->name('activities.details');

    // Certificates
    Route::resource('certificates', CertificateController::class);
    Route::get('/certificates/create-for', [CertificateController::class, 'createForAdmin'])->name('certificates.createForAdmin');
    Route::post('/certificates/store-for', [CertificateController::class, 'storeForAdmin'])->name('certificates.storeForAdmin');
    Route::get('/certificates/{certificate}/preview-design/{design}', [CertificateController::class, 'previewDesign'])->name('certificates.previewDesign');
    Route::get('/certificate/{id}/download', [CertificateController::class, 'download'])->name('certificates.download');
    Route::post('/certificates/generate-group/{group?}', [CertificateController::class, 'generateGroup'])->name('certificates.generateGroup');
    Route::get('/certificates/{certificate}/edit', [CertificateController::class, 'edit'])->name('certificates.edit');
    Route::post('/certificates/{certificate}/finalize', [CertificateController::class, 'finalize'])->name('certificates.finalize');

    Route::resource('certificate-requests', CertificateRequestController::class)->names('certificate_requests');
    Route::post('/certificate-requests/{certificateRequest}/approve', [CertificateRequestController::class, 'approve'])->name('certificate_requests.approve');
    Route::post('/certificate-requests/{certificateRequest}/reject', [CertificateRequestController::class, 'reject'])->name('certificate_requests.reject');


    Route::resource('certificate-templates', CertificateTemplateController::class);
    Route::get('/certificate-templates/{id}/preview', [CertificateTemplateController::class, 'preview'])->name('certificates.template_preview');

});

// Static pages
Route::get('/help', function () {
    return view('static.help');
})->name('help');
Route::get('/privacy-policy', function () {
    return view('static.privacy');
})->name('privacy-policy');
Route::get('/terms', function () {
    return view('static.terms');
})->name('terms');
Route::get('/verify-certificate/{certificate_number}', [CertificateController::class, 'verify'])->name('certificates.verify.public');

Route::get('/', function () {
    return view('static.welcome');
})->name('welcome');

