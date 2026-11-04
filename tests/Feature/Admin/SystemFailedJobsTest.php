<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed one failed job row for the system status page and actions.
 */
function failedJob(string $uuid): void
{
    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode([
            'displayName' => 'App\\Jobs\\SendLoanReminder',
        ]),
        'exception' => "RuntimeException: Mail gateway down\n#0 /app/Jobs/SendLoanReminder.php(42)",
        'failed_at' => now(),
    ]);
}

test('a super admin sees the failed jobs on the system page', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $uuid = (string) Str::uuid();

    failedJob($uuid);

    $this->actingAs($superAdmin)
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/system/index')
            ->has('failed_jobs', 1)
            ->where('failed_jobs.0.uuid', $uuid)
            ->where('failed_jobs.0.name', 'App\Jobs\SendLoanReminder')
            ->where('failed_jobs.0.queue', 'default')
            ->where('failed_jobs.0.exception', 'RuntimeException: Mail gateway down')
            ->where('failed_jobs.0.exception_detail', "RuntimeException: Mail gateway down\n#0 /app/Jobs/SendLoanReminder.php(42)")
        );
});

test('a super admin can retry a failed job', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $uuid = (string) Str::uuid();

    failedJob($uuid);

    $this->actingAs($superAdmin)
        ->post(route('admin.system.failed-jobs.retry', ['uuid' => $uuid]))
        ->assertRedirect();

    $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    $this->assertDatabaseCount('jobs', 1);
});

test('a super admin can forget a failed job', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $uuid = (string) Str::uuid();

    failedJob($uuid);

    $this->actingAs($superAdmin)
        ->delete(route('admin.system.failed-jobs.destroy', ['uuid' => $uuid]))
        ->assertRedirect();

    $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
});

test('an admin cannot see or work failed jobs', function () {
    $admin = User::factory()->admin()->create();
    $uuid = (string) Str::uuid();

    failedJob($uuid);

    $this->actingAs($admin)
        ->get(route('admin.system.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.system.failed-jobs.retry', ['uuid' => $uuid]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('admin.system.failed-jobs.destroy', ['uuid' => $uuid]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.system.failed-jobs.retry-all'))
        ->assertForbidden();
});

test('guests are sent to the login page from system status', function () {
    $this->get(route('admin.system.index'))
        ->assertRedirect(route('login'));
});
test('a super admin can retry every failed job at once', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    failedJob((string) Str::uuid());
    failedJob((string) Str::uuid());

    $this->actingAs($superAdmin)
        ->post(route('admin.system.failed-jobs.retry-all'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash): bool => $flash['toast']['type'] === 'success'
            && $flash['toast']['message'] === '2 failed jobs queued for retry.');

    $this->assertDatabaseCount('failed_jobs', 0);
    $this->assertDatabaseCount('jobs', 2);
});

test('retrying all failed jobs with an empty queue warns instead', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.system.failed-jobs.retry-all'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash): bool => $flash['toast']['type'] === 'warning'
            && $flash['toast']['message'] === 'There are no failed jobs to retry.');

    $this->assertDatabaseCount('jobs', 0);
});
