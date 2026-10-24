<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    /**
     * Models that may be filtered on, keyed by their short name.
     *
     * @var array<string, class-string>
     */
    private const SUBJECT_TYPES = [
        'Borrower' => Borrower::class,
        'Disbursement' => Disbursement::class,
        'Loan' => Loan::class,
        'LoanProduct' => LoanProduct::class,
        'Payment' => Payment::class,
        'User' => User::class,
    ];

    /**
     * Display the audit trail.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ActivityLog::class);

        $events = [
            ActivityLog::EVENT_CREATED,
            ActivityLog::EVENT_UPDATED,
            ActivityLog::EVENT_DELETED,
            ActivityLog::EVENT_LOGIN,
            ActivityLog::EVENT_LOGOUT,
            ActivityLog::EVENT_LOGIN_FAILED,
        ];

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', Rule::in($events)],
            'type' => ['nullable', Rule::in(array_keys(self::SUBJECT_TYPES))],
            'user' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $search = trim($filters['search'] ?? '');

        $logs = ActivityLog::query()
            ->with('user')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('description', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->whereAny(
                            ['name', 'email'],
                            'like',
                            "%{$search}%",
                        ));

                    if (ctype_digit($search)) {
                        $inner->orWhere('subject_id', (int) $search);
                    }
                });
            })
            ->when($filters['event'] ?? null, fn ($query, string $event) => $query->where('event', $event))
            ->when(
                $filters['type'] ?? null,
                fn ($query, string $type) => $query->where('subject_type', self::SUBJECT_TYPES[$type]),
            )
            ->when($filters['user'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->orderByDesc('id')
            ->paginate($this->perPage($request, 25))
            ->withQueryString();

        $existingSubjects = $this->existingSubjects($logs);

        $logs->through(fn (ActivityLog $log): array => [
            'id' => $log->id,
            'event' => $log->event,
            'event_label' => Str::headline($log->event),
            'description' => $log->description,
            'user_name' => $log->user?->name,
            'user_email' => $log->user?->email,
            'subject_type' => $log->subject_type !== null ? class_basename($log->subject_type) : null,
            'subject_id' => $log->subject_id,
            'subject_url' => $this->subjectUrl($log, $existingSubjects),
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'changes' => $this->changes($log),
            'created_at' => $log->created_at?->toIso8601String(),
        ]);

        return Inertia::render('admin/activity/index', [
            'logs' => $logs,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'event' => $filters['event'] ?? null,
                'type' => $filters['type'] ?? null,
                'user' => $filters['user'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'events' => collect($events)
                ->map(fn (string $event): array => [
                    'value' => $event,
                    'label' => Str::headline($event),
                ])
                ->all(),
            'types' => array_keys(self::SUBJECT_TYPES),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user): array => [
                    'value' => $user->id,
                    'label' => $user->name,
                ])
                ->all(),
        ]);
    }

    /**
     * Find which records on the current page still exist, keyed by type and id.
     *
     * @return array<string, true>
     */
    private function existingSubjects(LengthAwarePaginator $logs): array
    {
        $existing = [];

        $logs->getCollection()
            ->filter(fn (ActivityLog $log): bool => $log->subject_type !== null && $log->subject_id !== null)
            ->groupBy('subject_type')
            ->each(function (Collection $group, string $type) use (&$existing): void {
                if (! in_array($type, self::SUBJECT_TYPES, true)) {
                    return;
                }

                $ids = $type::query()
                    ->whereKey($group->pluck('subject_id')->unique()->all())
                    ->pluck('id');

                foreach ($ids as $id) {
                    $existing["{$type}:{$id}"] = true;
                }
            });

        return $existing;
    }

    /**
     * Get a link to the audited record when it still exists and has a page.
     *
     * @param  array<string, true>  $existingSubjects
     */
    private function subjectUrl(ActivityLog $log, array $existingSubjects): ?string
    {
        if ($log->subject_type === null || $log->subject_id === null) {
            return null;
        }

        if (! isset($existingSubjects["{$log->subject_type}:{$log->subject_id}"])) {
            return null;
        }

        return match ($log->subject_type) {
            Borrower::class => route('admin.borrowers.show', $log->subject_id),
            Loan::class => route('admin.loans.show', $log->subject_id),
            LoanProduct::class => route('admin.loan-products.edit', $log->subject_id),
            User::class => route('admin.users.edit', $log->subject_id),
            default => null,
        };
    }

    /**
     * Pair the old and new values of every changed attribute.
     *
     * @return array<int, array{key: string, old: string|null, new: string|null}>
     */
    private function changes(ActivityLog $log): array
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];

        $keys = array_values(array_unique([...array_keys($old), ...array_keys($new)]));

        return collect($keys)
            ->map(fn (string $key): array => [
                'key' => Str::headline($key),
                'old' => array_key_exists($key, $old) ? $this->displayValue($old[$key]) : null,
                'new' => array_key_exists($key, $new) ? $this->displayValue($new[$key]) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Render a single stored attribute value for display.
     */
    private function displayValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'Yes' : 'No',
            $value === null => '—',
            is_scalar($value) => (string) $value,
            default => json_encode($value) ?: '—',
        };
    }
}
