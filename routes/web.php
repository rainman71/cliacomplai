<?php

use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Cms209Controller;
use App\Http\Controllers\CompletenessCsvController;
use App\Http\Controllers\CompletenessPdfController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExecutiveCsvController;
use App\Http\Controllers\ExecutiveReportController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormPdfController;
use App\Http\Controllers\LabManagementController;
use App\Http\Controllers\LabProfileController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ReferenceLabApprovalController;
use App\Http\Controllers\SafetyChecklistController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WorklistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest / auth routes
|--------------------------------------------------------------------------
*/
Route::get('/login', [SessionController::class, 'create'])->name('login');
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
Route::get('/dev-login', DevLoginController::class)->name('dev.login'); // 404s outside local
Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated app
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    // Portfolio: the labs this user can access (redirects single-lab users straight in).
    Route::get('/', PortfolioController::class)->name('portfolio');

    // Cross-lab executive roll-up (multi-lab users / super admins).
    Route::get('/executive', ExecutiveReportController::class)->name('executive');
    Route::get('/executive/csv', ExecutiveCsvController::class)->name('executive.csv');

    // Merged, prioritized overdue queue across all the user's labs.
    Route::get('/worklist', WorklistController::class)->name('worklist');

    // Super-admin lab administration.
    Route::get('/admin/labs', LabManagementController::class)->middleware('can:manage-labs')->name('labs.index');

    // Everything below is scoped to one lab the user belongs to.
    Route::middleware('lab.member')->prefix('labs/{lab}')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/reports/completeness/csv', CompletenessCsvController::class)->name('reports.completeness.csv');
        Route::get('/reports/completeness/pdf', CompletenessPdfController::class)->name('reports.completeness.pdf');

        // Guided official-form wizards (answers stored → PDF + auto-completion).
        Route::get('/forms/safety-checklist', SafetyChecklistController::class)->name('forms.safety-checklist');
        Route::get('/forms/cms-209', Cms209Controller::class)->name('forms.cms-209');
        Route::get('/forms/reference-lab-approval', ReferenceLabApprovalController::class)->name('forms.reference-lab-approval');
        Route::get('/forms/{response}/pdf', FormPdfController::class)->name('forms.pdf');
        // Generic catalog-driven wizards (CMP-132, CMP-130, CMP-131, CMP-171, …). Registered after
        // the static/PDF routes so those match first; {code} only catches a single remaining segment.
        Route::get('/forms/show/{code}', FormController::class)->name('forms.show');

        Route::get('/users', UserManagementController::class)->middleware('can:manage-lab-users,lab')->name('users');
        Route::get('/profile', LabProfileController::class)->middleware('can:manage-lab-users,lab')->name('lab.profile');
    });
});
