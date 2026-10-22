<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\HttpSecurityScheme;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

final class AppServiceProvider extends ServiceProvider
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
        $this->configureRateLimiting();
        $this->configurePassport();
        $this->configureApiDocs();
        $this->configureEmailLinks();
    }

    /**
     * Document Passport bearer authentication in the Scramble OpenAPI spec.
     */
    private function configureApiDocs(): void
    {
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
            $openApi->secure(new HttpSecurityScheme('bearer')->as('bearer'));
        });
    }

    /**
     * Point password reset and email verification links to the frontend app.
     */
    private function configureEmailLinks(): void
    {
        ResetPassword::createUrlUsing(fn (mixed $user, string $token): string => $this->frontendUrl('/reset-password?').http_build_query([
            'token' => $token,
            'email' => $user instanceof CanResetPassword ? $user->getEmailForPasswordReset() : null,
        ]));

        // The frontend receives the signed API URL and POSTs to it with the user's bearer token.
        VerifyEmail::createUrlUsing(fn (User $user): string => $this->frontendUrl('/verify-email?').http_build_query([
            'url' => URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config()->integer('auth.verification.expire', 60)),
                ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
            ),
        ]));
    }

    private function frontendUrl(string $path): string
    {
        return mb_rtrim(config()->string('app.frontend_url'), '/').$path;
    }

    /**
     * Configure Passport token lifetimes.
     */
    private function configurePassport(): void
    {
        Passport::tokensExpireIn(now()->addDays(config()->integer('passport.tokens_expire_in_days', 15)));
        Passport::refreshTokensExpireIn(now()->addDays(config()->integer('passport.refresh_tokens_expire_in_days', 30)));
        Passport::personalAccessTokensExpireIn(now()->addMonths(config()->integer('passport.personal_access_tokens_expire_in_months', 6)));
    }

    /**
     * Configure the rate limiters for the application.
     */
    private function configureRateLimiting(): void
    {
        // Default API rate limiter - 60 requests per minute
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Auth endpoints - more restrictive (prevent brute force)
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Authenticated user requests - higher limit
        RateLimiter::for('authenticated', fn (Request $request) => $request->user()
            ? Limit::perMinute(120)->by($request->user()->id)
            : Limit::perMinute(60)->by($request->ip()));
    }
}
