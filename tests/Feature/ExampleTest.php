<?php

use App\Models\User;

test('guests are sent to the login screen', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('borrowers land on their dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});

test('administrators land on the admin dashboard', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('admin.dashboard'));
})->with(['admin', 'superAdmin']);
