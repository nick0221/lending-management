<?php

use App\Enums\KycStatus;
use App\Models\Borrower;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('admins can import borrowers from a csv', function () {
    $admin = User::factory()->admin()->create();

    $csv = <<<'CSV'
name,phone,email,national_id,date_of_birth,address,kyc_status,notes
Ada Lovelace,+1-555-0100,ada@example.com,NID-1000,1990-01-01,1 Engine Way,verified,First import
Grace Hopper,+1-555-0200,grace@example.com,NID-1001,,,,
CSV;

    $this->actingAs($admin)
        ->post(route('admin.borrowers.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('borrowers.csv', $csv),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.borrowers.imports.create'));

    expect(Borrower::query()->count())->toBe(2);

    $ada = Borrower::query()->where('national_id', 'NID-1000')->firstOrFail();
    $grace = Borrower::query()->where('national_id', 'NID-1001')->firstOrFail();

    expect($ada->name)->toBe('Ada Lovelace')
        ->and($ada->email)->toBe('ada@example.com')
        ->and($ada->kyc_status)->toBe(KycStatus::Verified)
        ->and($ada->date_of_birth?->toDateString())->toBe('1990-01-01')
        ->and($ada->notify_due_soon)->toBeTrue()
        ->and($grace->kyc_status)->toBe(KycStatus::Pending);
});

test('the import skips duplicate national ids and reports invalid rows', function () {
    $admin = User::factory()->admin()->create();
    Borrower::factory()->create([
        'name' => 'Existing Person',
        'national_id' => 'NID-EXISTING',
    ]);

    $csv = <<<'CSV'
name,phone,national_id,email
Existing Person,+1-555-0000,NID-EXISTING,
Valid Person,+1-555-0001,NID-NEW,valid@example.com
Missing Phone,,NID-MISSING,missing@example.com
Duplicate In File,+1-555-0002,NID-NEW,duplicate@example.com
CSV;

    $this->actingAs($admin)
        ->post(route('admin.borrowers.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('borrowers.csv', $csv),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.borrowers.imports.create'));

    expect(Borrower::query()->count())->toBe(2)
        ->and(Borrower::query()->where('national_id', 'NID-NEW')->exists())->toBeTrue();

    $this->get(route('admin.borrowers.imports.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/borrowers/import')
            ->where('summary.imported', 1)
            ->where('summary.skipped', 3)
            ->where('summary.total_rows', 4)
            ->where('summary.error_count', 3)
            ->has('summary.errors', 3)
            ->where('summary.errors.0.line', 2)
            ->where('summary.errors.0.message', 'National ID NID-EXISTING is already registered.')
            ->where('summary.errors.1.line', 4)
            ->where('summary.errors.2.line', 5)
        );
});

test('the import accepts common header aliases and a byte order mark', function () {
    $admin = User::factory()->admin()->create();

    $csv = "\xEF\xBB\xBFFull Name,Phone Number,Email Address\n"
        ."Ada Lovelace,+1-555-0100,ada@example.com\n";

    $this->actingAs($admin)
        ->post(route('admin.borrowers.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('borrowers.csv', $csv),
        ])
        ->assertSessionHasNoErrors();

    expect(Borrower::query()->where('name', 'Ada Lovelace')->exists())->toBeTrue();
});

test('the import requires name and phone headers', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.borrowers.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent(
                'borrowers.csv',
                "email,national_id\nada@example.com,NID-1\n",
            ),
        ])
        ->assertSessionHasErrors('file');

    expect(Borrower::query()->count())->toBe(0);
});

test('the import requires a csv file', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.borrowers.imports.store'), [])
        ->assertSessionHasErrors('file');
});

test('the import page renders without a previous summary', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.borrowers.imports.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/borrowers/import')
            ->where('summary', null)
        );
});

test('the csv template downloads with the expected headers', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.borrowers.imports.template'));

    $response->assertOk()
        ->assertDownload('borrowers-template.csv');

    expect($response->streamedContent())
        ->toContain('name,phone,email,national_id,date_of_birth,address,kyc_status,notes');
});

test('regular users cannot import borrowers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.borrowers.imports.create'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.borrowers.imports.store'), [])
        ->assertForbidden();
});
