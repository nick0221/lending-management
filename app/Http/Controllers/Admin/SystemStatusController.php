<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SystemStatusController extends Controller
{
    /**
     * Display the operational health of the application.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return Inertia::render('admin/system/index', [
            'app' => [
                'name' => config('app.name'),
                'environment' => config('app.env'),
                'debug' => (bool) config('app.debug'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'now' => now()->toIso8601String(),
            ],
            'database' => $this->databaseStatus(),
            'queue' => $this->queueStatus(),
            'scheduled' => $this->scheduledCommands(),
            'mail' => [
                'mailer' => config('mail.default'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ],
            'storage' => $this->storageStatus(),
        ]);
    }

    /**
     * Report the configured database and whether a trivial query succeeds.
     *
     * @return array<string, mixed>
     */
    private function databaseStatus(): array
    {
        $status = 'Connected';

        try {
            DB::select('select 1');
        } catch (Throwable) {
            $status = 'Unavailable';
        }

        return [
            'connection' => config('database.default'),
            'driver' => DB::connection()->getDriverName(),
            'status' => $status,
            'cache' => config('cache.default'),
            'session' => config('session.driver'),
        ];
    }

    /**
     * Report the queue backlog when the database queue is in use.
     *
     * @return array<string, mixed>
     */
    private function queueStatus(): array
    {
        $connection = config('queue.default');
        $pending = null;
        $failed = null;
        $oldest = null;

        if ($connection === 'database') {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            $oldestTimestamp = DB::table('jobs')->min('created_at');

            if ($oldestTimestamp !== null) {
                $oldest = Carbon::createFromTimestamp((int) $oldestTimestamp)->toIso8601String();
            }
        }

        return [
            'connection' => $connection,
            'pending' => $pending,
            'failed' => $failed,
            'oldest_pending_at' => $oldest,
        ];
    }

    /**
     * List the scheduled commands and when each one runs next.
     *
     * @return array<int, array<string, string>>
     */
    private function scheduledCommands(): array
    {
        return collect(app(Schedule::class)->events())
            ->map(function ($event): array {
                $command = (string) $event->command;

                return [
                    'command' => preg_replace('/^.*?artisan["\']?\s+/', '', $command) ?: $command,
                    'expression' => $event->expression,
                    'next_run_at' => $event->nextRunDate()->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Report storage disk usage and the configured disk drivers.
     *
     * @return array<string, mixed>
     */
    private function storageStatus(): array
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        $free = $free === false ? null : (float) $free;
        $total = $total === false ? null : (float) $total;

        $usedPercent = null;

        if ($free !== null && $total !== null && $total > 0) {
            $usedPercent = round((1 - $free / $total) * 100, 1);
        }

        return [
            'disk' => config('filesystems.default'),
            'path' => $path,
            'free' => $free === null ? null : $this->formatBytes($free),
            'total' => $total === null ? null : $this->formatBytes($total),
            'used_percent' => $usedPercent,
        ];
    }

    /**
     * Format a byte count for display.
     */
    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return number_format($bytes / (1024 ** $power), 1).' '.$units[$power];
    }
}
