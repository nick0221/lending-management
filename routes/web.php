<?php

use App\Http\Controllers\Admin\BorrowerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'role:super_admin,admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('users', UserController::class)->except('show');
        Route::resource('borrowers', BorrowerController::class)->except('show');
        Route::resource('loan-products', LoanProductController::class)->except('show');
        Route::resource('loans', LoanController::class)->except(['edit', 'update']);
        Route::post('loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
        Route::post('loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject');
        Route::post('loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('loans.disburse');
        Route::post('loans/{loan}/payments', [LoanController::class, 'recordPayment'])->name('loans.payments.store');
        Route::post('loans/{loan}/mark-default', [LoanController::class, 'markDefaulted'])->name('loans.mark-default');
        Route::post('loans/{loan}/write-off', [LoanController::class, 'writeOff'])->name('loans.write-off');
        Route::post('loans/{loan}/restructure', [LoanController::class, 'restructure'])->name('loans.restructure');
    });

require __DIR__.'/settings.php';
