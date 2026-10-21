<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\BorrowerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SystemStatusController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController as BorrowerDashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [BorrowerDashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:super_admin,admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('users', UserController::class)->except('show');
        Route::resource('borrowers', BorrowerController::class);
        Route::resource('loan-products', LoanProductController::class)->except('show');
        Route::post('loans/remind', [LoanController::class, 'sendReminders'])
            ->middleware('throttle:reminders')
            ->name('loans.remind');
        Route::post('loans/preview', [LoanController::class, 'preview'])->name('loans.preview');
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
            Route::post('loans/{loan}/mark-default', [LoanController::class, 'markDefaulted'])->name('loans.mark-default');
            Route::post('loans/{loan}/write-off', [LoanController::class, 'writeOff'])->name('loans.write-off');
            Route::post('loans/{loan}/restructure', [LoanController::class, 'restructure'])->name('loans.restructure');
            Route::post('loans/{loan}/statement', [LoanController::class, 'sendStatement'])->name('loans.statement');
        });

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/download/{report}', [ReportController::class, 'download'])
            ->whereIn('report', ['summary', 'loans', 'collections'])
            ->middleware('throttle:reports')
            ->name('reports.download');
        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
        Route::get('system', [SystemStatusController::class, 'index'])->name('system.index');
    });

require __DIR__.'/settings.php';
