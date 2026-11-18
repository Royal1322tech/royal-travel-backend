# B2B auth rate limits and the client IP

Covers three staging problems found on 2026-11-18, and how to roll out the fixes.

## What was wrong

1. **Every visitor had the same IP.**
   - Requests travel Cloudflare → Traefik (`royal-proxy`, 172.16.3.0/24) → nginx → php-fpm. Laravel trusted only 172.16.1.0/24, so `request()->ip()` returned Traefik's address (`172.16.3.3`) for everyone.
   - Traefik also doesn't trust Cloudflare. It replaces `X-Forwarded-For` with the address of the Cloudflare edge server, not the visitor.
2. **The auth endpoints shared one counter.**
   - The generic `throttle:N,M` key is `sha1(domain|ip)`. It doesn't include the route or the account.
   - So registration (10 per hour), login (5 per minute), OTP verification (5 per minute) and resend (3 per 5 minutes) all used one site-wide counter. The window was set by whichever route hit it first.
   - Result: real verification requests got 429 before the code was ever checked.
3. **`Retry-After` was hidden.** CORS didn't expose it, so the browser couldn't read how long a 429 lasts.

## Application changes (this branch)

**`app/Providers/AppServiceProvider.php`** now defines named limiters. Laravel prefixes each key with the limiter's name, so endpoints never share a counter.

| Limiter | Limits |
|---|---|
| `b2b-register` | 10 per hour per IP |
| `b2b-login` | 5 per minute per email and IP; 10 per minute per email from any IP; 20 per minute per IP |
| `b2b-verify-otp` | 5 per minute per email and company, from any IP; 20 per minute per IP. Each code also allows only 5 attempts and expires after 5 minutes |
| `b2b-resend-otp` | 3 per 5 minutes per email and company; 10 per hour per IP |

How the keys work:
- **Normalised:** emails are trimmed and lower-cased; values that aren't strings count as empty.
- **No enumeration:** keys are hashed and built only from what was submitted, before any database lookup, so a limit behaves the same whether or not the account exists.

The other changes:
- **`routes/api_v1.php`:** the four auth routes use `throttle:b2b-*`. The 429 response is unchanged (`{"message":"Too Many Attempts."}` plus `Retry-After`).
- **`config/cors.php`:** `exposed_headers => ['Retry-After']`. The allowed origin is still `FRONTEND_URL` only, with no wildcard and no credentials.
- **Tests:** `tests/Feature/B2B/B2BAuthRateLimitTest.php`, 19 tests.

## Infrastructure changes (not in this repository; each needs its own approval)

**Laravel**, in `/opt/royal-travel/staging/.env`:
```
TRUSTED_PROXIES=172.16.3.0/24,<Cloudflare IPv4 and IPv6 ranges>
```
The full value is in `.env.example`.

**Traefik**, in `/opt/royal-travel/shared/traefik/compose.yaml`, under `command:`:
```
- --entrypoints.web.forwardedHeaders.trustedIPs=<Cloudflare IPv4 and IPv6 ranges>
- --entrypoints.websecure.forwardedHeaders.trustedIPs=<Cloudflare IPv4 and IPv6 ranges>
```

**Cloudflare ranges:** take them from https://www.cloudflare.com/ips-v4 and https://www.cloudflare.com/ips-v6. Update them when Cloudflare publishes changes.

**The origin is reachable without Cloudflare.** That's why the trust list must contain only Traefik's network and Cloudflare's ranges, never `*`.

### Proxy lab (local Docker, Traefik v3.6.25, `illuminate/http` 12.70.2)

The lab ran staging's nginx site config and Laravel's own `TrustProxies` middleware. A test network stood in for Cloudflare's edge. All 17 checks passed:

- **Current configuration (reproduced the bug):** visitors on different edge servers, and a client connecting directly, all resolved to Traefik and shared one throttle key.
- **Proposed configuration:**
  - Two visitors resolve to separate addresses and keys.
  - The same visitor keeps one key across different edge servers.
  - An `X-Forwarded-For` the visitor sends through Cloudflare is ignored.
  - A client connecting directly can't spoof `X-Forwarded-For`, `CF-Connecting-IP` or `X-Forwarded-Host`, including by naming a trusted proxy.
  - HTTPS routing still works, and `isSecure()` stays true.
- **Only the Laravel change live:** addresses resolve to the Cloudflare edge server. That's already better than one shared key, and spoofing still fails.
- **Only the Traefik change live:** nothing changes until Laravel trusts Traefik.

## Rollout order

**Trust the proxy no later than the code change.** Until Laravel trusts Traefik, every visitor has the same IP, so the new per-IP limits would act as site-wide caps (for example, 10 resends per hour for all users together). The lab showed the Laravel-only proxy change is safe on its own.

1. **`TRUSTED_PROXIES`,** in the same maintenance window as the backend deploy, before it or with it:
   - Back up `.env` (`cp .env .env.backup.$(date +%Y%m%d-%H%M%S)`) and set the value.
   - The PHP container must be recreated to load it, because `env_file` is read when the container is created. The backend deploy in step 2 recreates it anyway. On its own: `docker compose -f compose.backend.yaml up -d php`, about 2 seconds of API downtime.
   - **Check:** throttle keys differ per Cloudflare edge server, and nothing else changes.
   - **Rollback:** restore the backup and recreate the container.
2. **Backend deploy** of this branch through the normal pipeline (PHP and nginx images).
   - **Check:** auth requests from two networks have separate counters, a 429 includes `Access-Control-Expose-Headers: Retry-After`, and `B2BAuthRateLimitTest` passes in CI.
   - **Rollback:** redeploy the previous image tag. The old generic throttles come back.
3. **Traefik:**
   - Back up `compose.yaml`.
   - Add the two flags, then run `docker compose up -d` in `/opt/royal-travel/shared/traefik`.
   - Traefik serves `staging.royalis.tech` and `staging-api.royalis.tech`. Expect about 2–5 seconds of downtime for both.
   - **Check:** both hosts return 200, and the log shows the visitor's IP in `X-Forwarded-For`.
   - **Rollback:** restore the compose backup and run `docker compose up -d`.

**Failure signs:** auth 5xx, 429 for first attempts, missing `Retry-After`, or either host failing its health check. If any appears, roll back that step only.
