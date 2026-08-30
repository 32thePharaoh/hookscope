<?php

namespace App\Providers;

use App\Capture\CaptureDropCounter;
use App\Replay\DnsResolver;
use App\Replay\PhpDnsResolver;
use App\Replay\ReplayTargetValidator;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        $this->app->singleton(DnsResolver::class, PhpDnsResolver::class);
        $this->app->bind(ReplayTargetValidator::class, function ($app): ReplayTargetValidator {
            $resolver = $app->make(DnsResolver::class);
            assert($resolver instanceof DnsResolver);

            return new ReplayTargetValidator(
                $resolver,
                (bool) config('hookscope.allow_private_targets'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureCaptureRateLimiting();
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

    protected function configureCaptureRateLimiting(): void
    {
        RateLimiter::for('capture', function (Request $request) {
            $token = (string) $request->route('token');

            return [
                Limit::perMinute((int) config('hookscope.throttle_per_minute'))
                    ->by('endpoint:'.$token)
                    ->response(function (Request $request, array $headers) use ($token) {
                        CaptureDropCounter::record($token);

                        return response()->json(['message' => 'Too many requests'], 429, $headers);
                    }),
                Limit::perMinute((int) config('hookscope.throttle_global_per_minute'))
                    ->by('global'),
            ];
        });
    }
}
