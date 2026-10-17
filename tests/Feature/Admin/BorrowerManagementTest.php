<?php

use App\Enums\KycStatus;
use App\Models\Borrower;
use App\Models\User;

test('the borrower list exposes the abilities for every row', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $borrower = Borrower::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($superAdmin)
        ->get(route('admin.borrowers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/borrowers/index')
            ->has('borrowers.data', 1)
            ->where('borrowers.data.0.name', 'Ada Lovelace')
            ->where('borrowers.data.0.kyc_status', KycStatus::Pending->value)
            ->where('borrowers.data.0.can.update', true)
            ->where('borrowers.data.0.can.delete', true)
        );
});

test('admins can register a borrower', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.borrowers.store'), [
            'name' => 'Grace Hopper',
            'national_id' => 'NID-123456',
            'phone' => '+1-555-0100',
            'email' => 'grace@example.com',
            'date_of_birth' => '1985-12-09',
            'address' => '1 Navy Yard, Arlington',
            'kyc_status' => KycStatus::Verified->value,
            'notes' => 'Priority customer.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.borrowers.index'));

    $borrower = Borrower::where('national_id', 'NID-123456')->first();

    expect($borrower)->not->toBeNull()
        ->and($borrower->name)->toBe('Grace Hopper')
        ->and($borrower->kyc_status)->toBe(KycStatus::Verified);
});

test('a borrower may optionally be linked to a user account', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.borrowers.store'), [
            'name' => 'Linked Borrower',
            'phone' => '+1-555-0111',
            'kyc_status' => KycStatus::Pending->value,
            'user_id' => $user->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Borrower::where('name', 'Linked Borrower')->first()?->user_id)->toBe($user->id);
});

test('registering a borrower validates the required fields and a unique national id', function () {
    $admin = User::factory()->admin()->create();
    Borrower::factory()->create(['national_id' => 'DUPLICATE']);

    $this->actingAs($admin)
        ->post(route('admin.borrowers.store'), [
            'name' => '',
            'phone' => '',
            'national_id' => 'DUPLICATE',
            'kyc_status' => 'not-a-status',
        ])
        ->assertSessionHasErrors(['name', 'phone', 'national_id', 'kyc_status']);
});

test('admins can update a borrower', function () {
    $admin = User::factory()->admin()->create();
    $borrower = Borrower::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.borrowers.update', $borrower), [
            'name' => 'Updated Borrower',
            'national_id' => $borrower->national_id,
            'phone' => '+1-555-0199',
            'email' => $borrower->email,
            'kyc_status' => KycStatus::Rejected->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.borrowers.index'));

    $borrower->refresh();

    expect($borrower->name)->toBe('Updated Borrower')
        ->and($borrower->kyc_status)->toBe(KycStatus::Rejected);
});

test('admins can delete a borrower', function () {
    $admin = User::factory()->admin()->create();
    $borrower = Borrower::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.borrowers.destroy', $borrower))
        ->assertRedirect(route('admin.borrowers.index'));

    expect($borrower->fresh())->toBeNull();
});
