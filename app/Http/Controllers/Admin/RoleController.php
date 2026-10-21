<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Services\RoleMatrix;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Show the read-only role matrix.
     */
    public function index(RoleMatrix $matrix): Response
    {
        return Inertia::render('admin/roles/index', [
            'groups' => $matrix->groups(),
            'roles' => array_map(fn (Role $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ], Role::cases()),
        ]);
    }
}
