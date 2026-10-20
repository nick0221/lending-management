<?php

use App\Models\User;

test('the reports page defaults to the portfolio tab', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/index')
            ->where('currentTab', 'portfolio')
            ->has('summary')
            ->has('aging')
            ->has('risk')
            ->has('collections')
            ->has('loans')
        );
});

test('the reports page selects the requested tab', function (string $tab) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['tab' => $tab]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentTab', $tab));
})->with(['aging', 'collections', 'arrears']);

test('the reports page falls back to the portfolio tab for an unknown tab', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['tab' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentTab', 'portfolio'));
});
