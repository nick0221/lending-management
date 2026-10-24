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
use Illuminate\Support\Facades\Password;
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
        $search = trim($request->string('search')->toString());
        $role = Role::tryFrom($request->string('role')->toString());

        $applySearch = fn ($query) => $query->whereAny(
            ['name', 'email'],
            'like',
            "%{$search}%",
        );

        $counts = User::query()
            ->when($search !== '', $applySearch)
            ->selectRaw('role, count(*) as aggregate')
            ->groupBy('role')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $row->role => (int) $row->aggregate,
            ]);

        $users = User::query()
            ->when($role, fn ($query) => $query->where('role', $role))
            ->when($search !== '', $applySearch)
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
            'roles' => $this->roleOptions(Role::cases()),
            'currentRole' => $role?->value,
            'search' => $search,
            'roleCounts' => [
                'all' => (int) $counts->sum(),
                ...collect(Role::cases())
                    ->mapWithKeys(fn (Role $case): array => [
                        $case->value => $counts->get($case->value, 0),
                    ])
                    ->all(),
            ],
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

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $role,
        ]);

        $user->sendEmailVerificationNotification();

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

        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.index');
    }

    /**
     * Send a fresh verification link to the given user.
     */
    public function sendVerificationEmail(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        if ($user->hasVerifiedEmail()) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('This account is already verified.'),
            ]);

            return back();
        }

        $user->sendEmailVerificationNotification();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Verification link sent to :email.', ['email' => $user->email]),
        ]);

        return back();
    }

    /**
     * Send a password reset link to the given user.
     */
    public function sendPasswordReset(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __($status),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Password reset link sent to :email.', ['email' => $user->email]),
        ]);

        return back();
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
