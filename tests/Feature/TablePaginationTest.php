<?php

use App\Models\Loan;
use App\Models\User;

test('the loan list accepts a supported page size', function () {
    $admin = User::factory()->admin()->create();
    Loan::factory()->count(20)->create();

    $this->actingAs($admin)
        ->get(route('admin.loans.index', ['per_page' => 50]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('loans.per_page', 50)
            ->has('loans.data', 20)
        );
});

test('an unsupported page size falls back to the list default', function () {
    $admin = User::factory()->admin()->create();
    Loan::factory()->count(20)->create();

    $this->actingAs($admin)
        ->get(route('admin.loans.index', ['per_page' => 500]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('loans.per_page', 15)
            ->has('loans.data', 15)
        );
});
