<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Services\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                'name' => app(Settings::class)->organisationName(),
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
            'sms' => $this->smsStatus(),
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
     * Report the SMS gateway configuration and recent delivery health.
     *
     * @return array<string, mixed>
     */
    private function smsStatus(): array
    {
        $sms = app(Settings::class)->smsConfig();
        $windowDays = 7;
        $since = now()->subDays($windowDays);

        $counts = NotificationLog::query()
            ->where('channel', NotificationLog::CHANNEL_SMS)
            ->where('created_at', '>=', $since)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $lastSent = NotificationLog::query()
            ->where('channel', NotificationLog::CHANNEL_SMS)
            ->whereNotNull('sent_at')
            ->latest('sent_at')
            ->first();

        $lastFailure = NotificationLog::query()
            ->where('channel', NotificationLog::CHANNEL_SMS)
            ->where('status', NotificationLog::STATUS_FAILED)
            ->latest('created_at')
            ->first();

        return [
            'driver' => $sms['driver'],
            'configured' => $sms['driver'] === 'log' || filled($sms['http']['url']),
            'endpoint' => $this->endpointHost($sms['http']['url']),
            'method' => $sms['http']['method'],
            'format' => $sms['http']['format'],
            'from' => $sms['from'],
            'window_days' => $windowDays,
            'deliveries' => [
                'sent' => (int) ($counts[NotificationLog::STATUS_SENT] ?? 0),
                'failed' => (int) ($counts[NotificationLog::STATUS_FAILED] ?? 0),
                'skipped' => (int) ($counts[NotificationLog::STATUS_SKIPPED] ?? 0),
                'queued' => (int) ($counts[NotificationLog::STATUS_QUEUED] ?? 0),
            ],
            'last_sent_at' => $lastSent?->sent_at?->toIso8601String(),
            'last_failure' => $lastFailure === null ? null : [
                'message' => Str::limit((string) $lastFailure->error, 200),
                'at' => $lastFailure->created_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Show only the gateway host so credentials in the URL never reach the page.
     */
    private function endpointHost(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        return parse_url($url, PHP_URL_HOST) ?: 'Configured';
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
