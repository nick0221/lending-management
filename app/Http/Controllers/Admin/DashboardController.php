<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReportingService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly ReportingService $reporting) {}

    /**
     * Show the administration overview.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('admin/dashboard', [
            'stats' => $this->roleCounts(),
            'summary' => $this->reporting->portfolioSummary(),
            'collections' => $this->reporting->collections(),
            'aging' => $this->reporting->agingBuckets(),
            'risk' => $this->reporting->portfolioAtRisk(),
            'recentPayments' => $this->reporting->recentPayments(5),
        ]);
    }

    /**
     * Get the number of users for each role.
     *
     * @return array{total: int, superAdmins: int, admins: int, users: int}
     */
    private function roleCounts(): array
    {
        $counts = User::query()
            ->toBase()
            ->selectRaw('role, count(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        return [
            'total' => User::query()->count(),
            'superAdmins' => (int) $counts->get(Role::SuperAdmin->value, 0),
            'admins' => (int) $counts->get(Role::Admin->value, 0),
            'users' => (int) $counts->get(Role::User->value, 0),
        ];
    }
}
