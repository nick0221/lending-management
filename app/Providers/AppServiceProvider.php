<?php

namespace App\Providers;

use App\Listeners\RecordAuthenticationActivity;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuditTrail();
        $this->configureRateLimiting();
    }

    /**
     * Throttle money-moving endpoints and report downloads per user.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('money', fn (Request $request): Limit => Limit::perMinute(
            app(Settings::class)->moneyRateLimit(),
        )->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('reports', fn (Request $request): Limit => Limit::perMinute(
            app(Settings::class)->reportsRateLimit(),
        )->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('reminders', fn (Request $request): Limit => Limit::perMinute(
            app(Settings::class)->remindersRateLimit(),
        )->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('search', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Record authentication events in the audit trail.
     */
    protected function configureAuditTrail(): void
    {
        Event::listen(Login::class, [RecordAuthenticationActivity::class, 'login']);
        Event::listen(Logout::class, [RecordAuthenticationActivity::class, 'logout']);
        Event::listen(Failed::class, [RecordAuthenticationActivity::class, 'failed']);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
