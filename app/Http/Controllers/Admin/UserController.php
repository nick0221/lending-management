<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();

        $users = User::query()
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (User $user): array => [
                ...$this->toPayload($user),
                'can' => [
                    'update' => $actor->can('update', $user),
                    'delete' => $actor->can('delete', $user),
                ],
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/create', [
            'roles' => $this->roleOptions($request->user()->assignableRolesFor()),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $role = Role::from($data['role']);

        Gate::authorize('assignRole', [new User, $role]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $role,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('admin.users.index');
    }

    /**
     * Show the form for editing the given user.
     */
    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/users/edit', [
            'user' => $this->toPayload($user),
            'roles' => $this->roleOptions($request->user()->assignableRolesFor($user)),
        ]);
    }

    /**
     * Update the given user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $role = Role::from($data['role']);

        Gate::authorize('assignRole', [$user, $role]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $role,
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.index');
    }

    /**
     * Remove the given user.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }

    /**
     * Transform the user into the payload shared with the client.
     *
     * @return array<string, mixed>
     */
    private function toPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Map the given roles to select options.
     *
     * @param  array<int, Role>  $roles
     * @return array<int, array{value: string, label: string}>
     */
    private function roleOptions(array $roles): array
    {
        return array_map(
            fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()],
            $roles,
        );
    }
}
