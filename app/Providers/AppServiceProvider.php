<?php

namespace App\Providers;

use App\Listeners\RecordAuthenticationActivity;
use App\Notifications\Channels\SmsChannel;
use App\Services\Settings;
use App\Services\Sms\HttpSmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsSender::class, function (): SmsSender {
            $driver = strtolower((string) config('services.sms.driver', 'log'));

            return match ($driver) {
                'log' => new LogSmsSender,
                'http' => new HttpSmsSender(
                    url: config('services.sms.http.url'),
                    method: config('services.sms.http.method', 'POST'),
                    format: config('services.sms.http.format', 'json'),
                    token: config('services.sms.http.token'),
                    tokenHeader: config('services.sms.http.token_header', 'Authorization'),
                    tokenPrefix: config('services.sms.http.token_prefix', 'Bearer '),
                    headers: config('services.sms.http.headers', []),
                    toField: config('services.sms.http.to_field', 'to'),
                    messageField: config('services.sms.http.message_field', 'message'),
                    from: config('services.sms.from'),
                    fromField: config('services.sms.http.from_field', 'from'),
                    timeout: (int) config('services.sms.http.timeout', 10),
                    connectTimeout: (int) config('services.sms.http.connect_timeout', 3),
                ),
                default => throw new InvalidArgumentException(
                    "Unsupported SMS driver [{$driver}].",
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuditTrail();
        $this->configureNotifications();
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
     * Register the application-specific SMS notification channel.
     */
    protected function configureNotifications(): void
    {
        Notification::extend(
            'sms',
            fn ($app): SmsChannel => $app->make(SmsChannel::class),
        );
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
