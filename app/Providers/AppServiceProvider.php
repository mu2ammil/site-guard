<?php

namespace App\Providers;

use App\Models\AuditEvent;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        Event::listen(Login::class, fn (Login $event) => AuditEvent::record(request(), 'login', ['user_id' => $event->user->getAuthIdentifier()]));
        Event::listen(Failed::class, fn (Failed $event) => AuditEvent::record(request(), 'login_failed', ['email' => substr((string) ($event->credentials['email'] ?? ''), 0, 120)]));
        Event::listen(Logout::class, fn (Logout $event) => AuditEvent::record(request(), 'logout', ['user_id' => $event->user->getAuthIdentifier()]));
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
