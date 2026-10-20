<?php

use App\Models\User;

test('guests are redirected to the login page from the admin panel', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with([
    'dashboard' => 'admin.dashboard',
    'users index' => 'admin.users.index',
    'users create' => 'admin.users.create',
    'borrowers index' => 'admin.borrowers.index',
    'borrowers create' => 'admin.borrowers.create',
    'loan products index' => 'admin.loan-products.index',
    'loan products create' => 'admin.loan-products.create',
    'loans index' => 'admin.loans.index',
    'loans create' => 'admin.loans.create',
    'reports index' => 'admin.reports.index',
]);

test('regular users are forbidden from the admin panel', function (string $route) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route($route))->assertForbidden();
})->with([
    'dashboard' => 'admin.dashboard',
    'users index' => 'admin.users.index',
    'users create' => 'admin.users.create',
    'borrowers index' => 'admin.borrowers.index',
    'borrowers create' => 'admin.borrowers.create',
    'loan products index' => 'admin.loan-products.index',
    'loan products create' => 'admin.loan-products.create',
    'loans index' => 'admin.loans.index',
    'loans create' => 'admin.loans.create',
    'reports index' => 'admin.reports.index',
]);

test('admins can access the admin panel', function (string $route) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route($route))->assertOk();
})->with([
    'dashboard' => 'admin.dashboard',
    'users index' => 'admin.users.index',
    'users create' => 'admin.users.create',
    'borrowers index' => 'admin.borrowers.index',
    'borrowers create' => 'admin.borrowers.create',
    'loan products index' => 'admin.loan-products.index',
    'loan products create' => 'admin.loan-products.create',
    'loans index' => 'admin.loans.index',
    'loans create' => 'admin.loans.create',
    'reports index' => 'admin.reports.index',
]);

test('super admins can access the admin panel', function (string $route) {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)->get(route($route))->assertOk();
})->with([
    'dashboard' => 'admin.dashboard',
    'users index' => 'admin.users.index',
    'users create' => 'admin.users.create',
    'borrowers index' => 'admin.borrowers.index',
    'borrowers create' => 'admin.borrowers.create',
    'loan products index' => 'admin.loan-products.index',
    'loan products create' => 'admin.loan-products.create',
    'loans index' => 'admin.loans.index',
    'loans create' => 'admin.loans.create',
    'reports index' => 'admin.reports.index',
]);
