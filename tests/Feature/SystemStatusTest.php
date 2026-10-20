<?php

use App\Models\User;

test('super admins can see the system status', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/system/index')
            ->where('app.laravel', app()->version())
            ->where('app.php', PHP_VERSION)
            ->where('database.connection', config('database.default'))
            ->where('database.status', 'Connected')
            ->where('queue.connection', config('queue.default'))
            ->where('mail.mailer', config('mail.default'))
            ->has('scheduled')
        );
});

test('admins cannot see the system status', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.system.index'))
        ->assertForbidden();
});
