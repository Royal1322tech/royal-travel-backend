<?php
namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Modules\SuppliersPricing\Contracts\FxRateProviderInterface;
use App\Modules\SuppliersPricing\Providers\OpenExchangeRatesProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FxRateProviderInterface::class, OpenExchangeRatesProvider::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('staging')) {
            URL::forceScheme('https');
        }

        $this->configureB2BAuthRateLimits();
    }

    /**
     * Named limiters for the public B2B auth endpoints.
     *
     * The generic `throttle:N,M` middleware keys every route on the client IP alone, so registration,
     * login, OTP verification and resend shared one counter, and the first route to hit it decided the
     * window. Each limiter here has its own name (Laravel prefixes the key with it), so endpoints never
     * share a counter. Each one combines:
     * - an account limit (normalised email, plus company for OTP), so one account can't be brute-forced
     *   from many IPs and one user's mistakes don't block another user;
     * - an IP limit, so rotating email addresses can't bypass the account limit.
     *
     * Keys are hashed and built from the submitted values only, before any lookup, so a limit behaves the
     * same whether or not the account exists (no enumeration). Missing or malformed values fall into
     * one shared "unknown" bucket and are still bounded by the IP limit.
     */
    private function configureB2BAuthRateLimits(): void
    {
        $identifier = static function (Request $request, string $field): string {
            $value = $request->input($field);

            return is_string($value) ? strtolower(trim($value)) : '';
        };
        $account = static fn (string ...$parts): string => 'acct:'.hash('sha256', implode('|', $parts));
        $ip = static fn (Request $request): string => 'ip:'.$request->ip();

        RateLimiter::for('b2b-register', fn (Request $request) => [
            Limit::perHour(10)->by($ip($request)),
        ]);

        RateLimiter::for('b2b-login', fn (Request $request) => [
            // Ordinary mistakes: the same account from the same network.
            Limit::perMinute(5)->by($account($identifier($request, 'email'), (string) $request->ip())),
            // Distributed guessing against one account from many networks.
            Limit::perMinute(10)->by($account($identifier($request, 'email'))),
            // Many accounts from one network.
            Limit::perMinute(20)->by($ip($request)),
        ]);

        RateLimiter::for('b2b-verify-otp', fn (Request $request) => [
            // Each code also allows only 5 attempts and expires after 5 minutes (B2BRegistrationOtpService).
            Limit::perMinute(5)->by($account($identifier($request, 'email'), $identifier($request, 'company_id'))),
            Limit::perMinute(20)->by($ip($request)),
        ]);

        RateLimiter::for('b2b-resend-otp', fn (Request $request) => [
            // Every accepted resend may email a code: keep the mailbox and the sender from being flooded.
            Limit::perMinutes(5, 3)->by($account($identifier($request, 'email'), $identifier($request, 'company_id'))),
            Limit::perHour(10)->by($ip($request)),
        ]);
    }
}
