<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RoleMatrix;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Show the read-only role matrix.
     */
    public function index(RoleMatrix $matrix): Response
    {
        Gate::authorize('viewAny', User::class);

        $counts = User::query()
            ->selectRaw('role, count(*) as aggregate')
            ->groupBy('role')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $row->role => (int) $row->aggregate,
            ]);

        return Inertia::render('admin/roles/index', [
            'groups' => $matrix->groups(),
            'roles' => array_map(fn (Role $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ], Role::cases()),
            'counts' => collect(Role::cases())
                ->mapWithKeys(fn (Role $role): array => [
                    $role->value => $counts->get($role->value, 0),
                ])
                ->all(),
        ]);
    }
}
