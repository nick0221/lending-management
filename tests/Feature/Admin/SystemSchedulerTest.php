<?php

use App\Models\User;
use App\Services\OpsHeartbeat;
use Illuminate\Support\Facades\Cache;

test('the heartbeat records and reads the last successful run', function () {
    $heartbeat = app(OpsHeartbeat::class);

    expect($heartbeat->lastRun('loans:flag-overdue'))->toBeNull();

    $heartbeat->record('loans:flag-overdue');

    expect($heartbeat->lastRun('loans:flag-overdue'))->toBeString();
});

test('the system page flags a scheduled command with no heartbeat', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('scheduled', function ($scheduled): bool {
            $event = collect($scheduled)->firstWhere('command', 'loans:flag-overdue');

            return $event !== null
                && $event['last_run_at'] === null
                && $event['stale'] === true;
        }));
});

test('the system page reports a fresh scheduled command heartbeat', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    Cache::put(
        'ops:heartbeat:loans:flag-overdue',
        now()->subHour()->toIso8601String(),
    );

    $this->actingAs($superAdmin)
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('scheduled', function ($scheduled): bool {
            $event = collect($scheduled)->firstWhere('command', 'loans:flag-overdue');

            return $event !== null
                && $event['last_run_at'] !== null
                && $event['stale'] === false;
        }));
});
