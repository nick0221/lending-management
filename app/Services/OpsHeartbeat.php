<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * Records when a scheduled command last completed successfully so the
 * system status page can warn when the scheduler stops running.
 */
class OpsHeartbeat
{
    /**
     * Store the current time as the command's last successful run.
     */
    public function record(string $command): void
    {
        Cache::forever($this->key($command), Date::now()->toIso8601String());
    }

    /**
     * Read the command's last successful run, if it has been recorded.
     */
    public function lastRun(string $command): ?string
    {
        $value = Cache::get($this->key($command));

        return is_string($value) ? $value : null;
    }

    /**
     * Build the cache key for a scheduled command.
     */
    private function key(string $command): string
    {
        return "ops:heartbeat:{$command}";
    }
}
