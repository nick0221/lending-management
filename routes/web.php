<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\BorrowerController;
use App\Http\Controllers\Admin\BorrowerImportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SystemStatusController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController as BorrowerDashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [BorrowerDashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:super_admin,admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('search', SearchController::class)
            ->middleware('throttle:search')
            ->name('search');
        Route::post('users/{user}/verification', [UserController::class, 'sendVerificationEmail'])
            ->middleware('throttle:reminders')
            ->name('users.verification');
        Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])
            ->middleware('throttle:reminders')
            ->name('users.password-reset');
        Route::resource('users', UserController::class)->except('show');
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('borrowers/import', [BorrowerImportController::class, 'create'])->name('borrowers.imports.create');
        Route::post('borrowers/import', [BorrowerImportController::class, 'store'])->name('borrowers.imports.store');
        Route::get('borrowers/import/template', [BorrowerImportController::class, 'template'])->name('borrowers.imports.template');
        Route::get('borrowers/import/errors', [BorrowerImportController::class, 'downloadErrors'])->name('borrowers.imports.errors');
        Route::patch('borrowers/kyc-status', [BorrowerController::class, 'updateKycStatus'])
            ->name('borrowers.kyc-status');
        Route::resource('borrowers', BorrowerController::class);
        Route::patch('loan-products/{loanProduct}/availability', [LoanProductController::class, 'toggleAvailability'])
            ->name('loan-products.availability');
        Route::resource('loan-products', LoanProductController::class)->except('show');
        Route::post('loans/remind', [LoanController::class, 'sendReminders'])
            ->middleware('throttle:reminders')
            ->name('loans.remind');
        Route::post('loans/bulk-approve', [LoanController::class, 'approveBulk'])
            ->middleware('throttle:money')
            ->name('loans.bulk-approve');
        Route::post('loans/preview', [LoanController::class, 'preview'])->name('loans.preview');
        Route::get('loans/calculator', [LoanController::class, 'calculator'])->name('loans.calculator');
        Route::resource('loans', LoanController::class)->except(['edit', 'update']);
        Route::get('loans/{loan}/payments/{payment}/receipt', [LoanController::class, 'receipt'])
            ->name('loans.payments.receipt');
        Route::post('loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
        Route::post('loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject');

        Route::middleware('throttle:money')->group(function () {
            Route::post('loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('loans.disburse');
            Route::post('loans/{loan}/payments', [LoanController::class, 'recordPayment'])->name('loans.payments.store');
            Route::post('loans/{loan}/payments/{payment}/receipt/email', [LoanController::class, 'emailReceipt'])
                ->name('loans.payments.receipt.email');
            Route::post('loans/{loan}/payments/{payment}/reverse', [LoanController::class, 'reversePayment'])
                ->name('loans.payments.reverse');
            Route::post('loans/{loan}/mark-default', [LoanController::class, 'markDefaulted'])->name('loans.mark-default');
            Route::post('loans/{loan}/write-off', [LoanController::class, 'writeOff'])->name('loans.write-off');
            Route::post('loans/{loan}/restructure', [LoanController::class, 'restructure'])->name('loans.restructure');
            Route::post('loans/{loan}/statement', [LoanController::class, 'sendStatement'])->name('loans.statement');
            Route::get('loans/{loan}/statement/preview', [LoanController::class, 'previewStatement'])->name('loans.statement.preview');
        });

        Route::get('loans/{loan}/statement/print', [LoanController::class, 'printStatement'])
            ->middleware('throttle:reports')
            ->name('loans.statement.print');

        Route::get('loans/{loan}/statement/download', [LoanController::class, 'downloadStatement'])
            ->middleware('throttle:reports')
            ->name('loans.statement.download');

        Route::post('loans/{loan}/notifications/{notificationLog}/resend', [LoanController::class, 'resendNotification'])
            ->middleware('throttle:reminders')
            ->name('loans.notifications.resend');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/print', [ReportController::class, 'printSummary'])->name('reports.print');
        Route::get('reports/download/{report}', [ReportController::class, 'download'])
            ->whereIn('report', ['summary', 'loans', 'collections'])
            ->middleware('throttle:reports')
            ->name('reports.download');
        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
        Route::get('system', [SystemStatusController::class, 'index'])->name('system.index');

        Route::middleware('role:super_admin')->group(function () {
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        });
    });

require __DIR__.'/settings.php';
