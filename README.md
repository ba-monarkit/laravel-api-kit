# Laravel API Kit

A production-ready, API-only Laravel 13 starter kit following the 2025-2026 REST API ecosystem best practices. No frontend dependencies - purely headless API for mobile apps, SPAs, or microservices.

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-13.x-red)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

## Features

- **API-Only** - No Blade, Vite, or frontend assets
- **OAuth2 Authentication** - [Laravel Passport](https://laravel.com/docs/passport) personal access tokens, plus a full OAuth2 server for machine-to-machine and third-party clients
- **Social Login** - GitHub & Google out of the box via [Laravel Socialite](https://laravel.com/docs/socialite)
- **Debugging** - [Laravel Telescope](https://laravel.com/docs/telescope) for requests, queries, jobs, mail, logs and exceptions
- **Email Testing** - [Mailpit](https://mailpit.axllent.org) catches every local email (UI on port 8025)
- **Queued Emails** - Verification & password-reset emails are sent by a queue worker (Redis in Docker)
- **AI-Ready** - [Laravel Boost](https://laravel.com/docs/boost) MCP server, guidelines and skills for AI coding agents
- **Docker** - Dev stack (app, nginx, MySQL, Redis, Mailpit, queue, scheduler) and a production stack
- **Email Verification** - Built-in email verification flow with signed URLs
- **Password Reset** - Secure password reset with token-based flow
- **API Versioning** - URI-based versioning with deprecation support via [grazulex/laravel-apiroute](https://github.com/Grazulex/laravel-apiroute)
- **Query Building** - Filtering, sorting, includes via [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder)
- **Data Objects** - Type-safe DTOs via [spatie/laravel-data](https://github.com/spatie/laravel-data)
- **Auto Documentation** - Zero-annotation OpenAPI 3.1 via [dedoc/scramble](https://github.com/dedoc/scramble)
- **Modern Testing** - Pest PHP with Laravel HTTP testing
- **Code Quality** - PHPStan (max level), Rector, and Pint with strict rules
- **Rate Limiting** - Configurable per-route rate limiters
- **Reusable Middleware** - ForceJsonResponse, LogApiRequests, EnsureEmailVerified
- **Standardized Responses** - Consistent JSON response format
- **Optional: API Idempotency** - RFC-compliant idempotency via [grazulex/laravel-api-idempotency](https://github.com/Grazulex/laravel-api-idempotency)
- **Optional: Smart Rate Limiting** - Plan-aware throttling with quotas via [grazulex/laravel-api-throttle-smart](https://github.com/Grazulex/laravel-api-throttle-smart)

## Requirements

- Docker & Docker Compose
- Or: PHP 8.4+, Composer 2.x

## Quick Start

### With Docker (Recommended)

```bash
# Clone the repository
git clone https://github.com/grazulex/laravel-api-kit.git
cd laravel-api-kit

# Copy environment file
cp .env.example .env

# Build and start the stack. On first boot the app container runs
# composer install, migrations and Passport setup automatically.
docker compose up -d --build

# Generate application key
docker compose exec app php artisan key:generate

# Run tests to verify installation
docker compose exec app composer test
```

| Service | URL |
|---------|-----|
| API | http://localhost:8000/api/v1 |
| API docs (Scramble) | http://localhost:8000/docs/api |
| Telescope | http://localhost:8000/telescope |
| Mailpit | http://localhost:8025 |

See [Docker](#docker) for details on every service.

### Without Docker

```bash
# Clone and install
git clone https://github.com/grazulex/laravel-api-kit.git
cd laravel-api-kit
composer install

# Configure
cp .env.example .env
php artisan key:generate

# Database (SQLite by default)
touch database/database.sqlite
php artisan migrate

# Passport encryption keys + personal access client (idempotent)
php artisan app:passport-setup

# Verify
./vendor/bin/pest

# Run each in its own terminal
php artisan serve        # API on http://localhost:8000
php artisan queue:work   # sends queued emails
php artisan pail         # live log tail
```

> Emails are sent over SMTP to `127.0.0.1:1025`. Start Mailpit with
> `docker compose up -d mailpit` (or set `MAIL_MAILER=log`).

## API Documentation

Documentation is generated from the code by [Scramble](https://scramble.dedoc.co) - no annotations needed:

- **Docs UI (Stoplight Elements)**: [http://localhost:8000/docs/api](http://localhost:8000/docs/api)
- **OpenAPI JSON**: [http://localhost:8000/docs/api.json](http://localhost:8000/docs/api.json)

Every endpoint is documented with a Bearer security scheme, so you can paste a Passport token into
"Try It". Public endpoints are marked with an `@unauthenticated` PHPDoc tag on the controller method.

> Scramble only serves the docs in the `local` environment. To expose them elsewhere, define a
> `viewApiDocs` gate (see the [Scramble docs](https://scramble.dedoc.co/usage/getting-started#docs-authorization)).

## Authentication

This kit uses **Laravel Passport**, a full OAuth2 server. Register/login return a personal access token
(a signed JWT) that clients send as a Bearer token. The `api` guard (`auth:api`) uses the `passport` driver.

Passport needs RSA keys and a personal access client. `php artisan app:passport-setup` creates both if
they are missing; the Docker entrypoint and `composer create-project` run it automatically.

| Setting | Env variable | Default |
|---------|--------------|---------|
| Access token lifetime | `PASSPORT_TOKENS_EXPIRE_IN_DAYS` | 15 days |
| Refresh token lifetime | `PASSPORT_REFRESH_TOKENS_EXPIRE_IN_DAYS` | 30 days |
| Personal access token lifetime | `PASSPORT_PERSONAL_ACCESS_TOKENS_EXPIRE_IN_MONTHS` | 6 months |
| Keys from env instead of `storage/` | `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` | - |

Need other OAuth2 flows? Create a client with `php artisan passport:client` and use the
`/oauth/token` endpoint Passport registers. Client credentials (`--client`, machine-to-machine) work
as-is. The password grant must first be enabled with `Passport::enablePasswordGrant()`, and the
authorization-code and device flows need a web login/consent screen, which this API-only kit does not include. Expired and revoked tokens are purged hourly by the scheduler.

### Register a New User

```bash
curl -X POST http://localhost:8000/api/v1/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "User registered successfully. Please check your email to verify your account.",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "email_verified_at": null,
      "created_at": "2025-01-15T10:30:00+00:00"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
  }
}
```

### Login

```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

### Using the Token

Include the token in the `Authorization` header for protected routes:

```bash
curl -X GET http://localhost:8000/api/v1/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..." \
  -H "Accept: application/json"
```

### Logout

```bash
curl -X POST http://localhost:8000/api/v1/logout \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..." \
  -H "Accept: application/json"
```

### Email Verification

After registration, users receive a verification email. The kit integrates with Laravel's `MustVerifyEmail` contract.

The link in the email points to your frontend: `FRONTEND_URL/verify-email?url=<signed API URL>`.
The frontend POSTs to that signed URL with the user's token:

**Verify Email (via signed URL from email):**
```bash
curl -X POST "http://localhost:8000/api/v1/email/verify/{id}/{hash}?signature=..." \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..." \
  -H "Accept: application/json"
```

**Resend Verification Email:**
```bash
curl -X POST http://localhost:8000/api/v1/email/resend \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..." \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "john@example.com"}'
```

**Response:**
```json
{
  "success": true,
  "message": "Verification email sent successfully",
  "data": null
}
```

### Password Reset

**Request Password Reset Link:**
```bash
curl -X POST http://localhost:8000/api/v1/forgot-password \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "john@example.com"}'
```

**Response:**
```json
{
  "success": true,
  "message": "Password reset link sent to your email",
  "data": null
}
```

The email links to `FRONTEND_URL/reset-password?token=...&email=...`; your frontend then calls:

**Reset Password (with token from email):**
```bash
curl -X POST http://localhost:8000/api/v1/reset-password \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john@example.com",
    "token": "reset-token-from-email",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Password reset successfully",
  "data": null
}
```

> **Note:** After a successful password reset, all user tokens are revoked for security.

### Social Login (Socialite)

GitHub and Google are enabled by default (`services.socialite_providers` in `config/services.php`).
The flow is stateless, which suits SPAs and mobile apps:

1. The client calls `GET /api/v1/auth/{provider}/redirect` and receives the provider's authorization URL.
2. The user signs in at the provider, which redirects to `/api/v1/auth/{provider}/callback?code=...`.
3. The callback creates (or links, by email) the user, marks the email verified and returns
   `{ user, token }`, the same as `/login`.

```bash
curl http://localhost:8000/api/v1/auth/github/redirect -H "Accept: application/json"
```

Configure credentials in `.env`:

```env
GITHUB_CLIENT_ID=...
GITHUB_CLIENT_SECRET=...
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
```

Accounts created through social login have no password, so `/login` rejects them; they can set one
with the password-reset flow. To add a provider, add its credentials to `config/services.php` and its
name to `socialite_providers`.

## API Endpoints

### Version 1 (`/api/v1`)

| Method | Endpoint                     | Auth | Description                   | Rate Limit |
|--------|------------------------------|------|-------------------------------|------------|
| POST   | /register                    | No   | Register new user             | 5/min      |
| POST   | /login                       | No   | Get authentication token      | 5/min      |
| POST   | /logout                      | Yes  | Revoke current token          | 120/min    |
| GET    | /me                          | Yes  | Get current user profile      | 120/min    |
| POST   | /email/verify/{id}/{hash}    | Yes  | Verify email address          | 120/min    |
| POST   | /email/resend                | Yes  | Resend verification email     | 6/min      |
| POST   | /forgot-password             | No   | Request password reset link   | 6/min      |
| POST   | /reset-password              | No   | Reset password with token     | 6/min      |
| GET    | /auth/{provider}/redirect    | No   | Social login: get provider URL | 5/min     |
| GET    | /auth/{provider}/callback    | No   | Social login: issue token     | 5/min      |

## Response Format

All API responses follow a consistent format:

### Success Response

```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    // Response data here
  }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200  | Success |
| 201  | Resource created |
| 204  | No content |
| 400  | Bad request |
| 401  | Unauthorized |
| 403  | Forbidden |
| 404  | Not found |
| 422  | Validation error |
| 429  | Too many requests |
| 500  | Server error |

## Project Structure

```
laravel-api-kit/
├── app/
│   ├── Actions/                    # Single-purpose action classes
│   ├── DTOs/                       # Data Transfer Objects (spatie/laravel-data)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── ApiController.php      # Base controller with ApiResponse
│   │   │       └── V1/                    # Version 1 controllers
│   │   │           ├── AuthController.php
│   │   │           └── SocialAuthController.php   # Socialite login
│   │   ├── Requests/
│   │   │   └── Api/V1/                    # Form Requests per version
│   │   │       ├── LoginRequest.php
│   │   │       └── RegisterRequest.php
│   │   └── Resources/                     # API Resources
│   │       └── UserResource.php
│   ├── Models/
│   │   └── User.php                       # Passport HasApiTokens + OAuthenticatable
│   ├── Notifications/                     # Queued verify-email / reset-password mails
│   ├── Providers/
│   │   ├── AppServiceProvider.php         # Rate limiting, Passport, docs, email links
│   │   └── TelescopeServiceProvider.php   # Telescope filters and access gate
│   ├── Services/                          # Business logic services
│   └── Traits/
│       └── ApiResponse.php                # Standardized responses
├── config/
│   ├── apiroute.php                       # API versioning config
│   ├── cors.php                           # CORS settings
│   ├── passport.php                       # OAuth2 / token lifetimes
│   ├── scramble.php                       # API docs config
│   ├── services.php                       # Socialite providers
│   └── telescope.php                      # Debug dashboard config
├── docker/
│   ├── entrypoint.sh                      # Migrations + Passport setup on boot
│   ├── nginx/default.conf
│   └── php/                               # local.ini (dev), production.ini (opcache)
├── routes/
│   ├── api.php                            # API routes entry point
│   ├── console.php                        # app:passport-setup + scheduled pruning
│   └── api/
│       └── v1.php                         # Version 1 routes
├── tests/
│   └── Feature/Api/V1/
│       └── AuthTest.php                   # Authentication tests
├── docker-compose.yml                     # Development stack
├── docker-compose.prod.yml                # Production stack
├── Dockerfile                             # Multi-stage: dev, prod, nginx
├── boost.json / .mcp.json / AGENTS.md     # Laravel Boost (AI agents)
└── CLAUDE.md                              # AI assistant instructions
```

## API Versioning

This kit uses [grazulex/laravel-apiroute](https://github.com/Grazulex/laravel-apiroute) v2.x for API versioning with support for:

- **URI Path** (default): `/api/v1/users`, `/api/v2/users`
- **Header**: `X-API-Version: 2`
- **Query Parameter**: `?api_version=2`
- **Accept Header**: `Accept: application/vnd.api.v2+json`

### Adding a New API Version

1. Create controllers in `app/Http/Controllers/Api/V2/`
2. Create requests in `app/Http/Requests/Api/V2/`
3. Create route file `routes/api/v2.php`:

```php
<?php

use App\Http\Controllers\Api\V2\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
// ... more routes
```

4. Update `config/apiroute.php`:

```php
'versions' => [
    'v1' => [
        'routes' => base_path('routes/api/v1.php'),
        'status' => 'deprecated',
        'deprecated_at' => '2025-06-01',
        'sunset_at' => '2025-12-01',
        'successor' => 'v2',
    ],
    'v2' => [
        'routes' => base_path('routes/api/v2.php'),
        'status' => 'active',
    ],
],
```

### Deprecation Headers

When accessing deprecated versions, responses include RFC-compliant headers:

```http
Deprecation: Sun, 01 Jun 2025 00:00:00 GMT
Sunset: Mon, 01 Dec 2025 00:00:00 GMT
Link: </api/v2>; rel="successor-version"
```

## Query Building

Use [spatie/laravel-query-builder](https://spatie.be/docs/laravel-query-builder) for filtering, sorting, and including relationships:

```php
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

// In your controller
$users = QueryBuilder::for(User::class)
    ->allowedFilters([
        'name',
        'email',
        AllowedFilter::exact('id'),
        AllowedFilter::scope('active'),
    ])
    ->allowedSorts(['name', 'created_at'])
    ->allowedIncludes(['posts', 'comments'])
    ->paginate();

return UserResource::collection($users);
```

**Request examples:**
```
GET /api/v1/users?filter[name]=john
GET /api/v1/users?sort=-created_at
GET /api/v1/users?include=posts,comments
GET /api/v1/users?filter[name]=john&sort=name&include=posts
```

## Data Transfer Objects

Use [spatie/laravel-data](https://spatie.be/docs/laravel-data) for type-safe DTOs:

```php
// app/DTOs/UserData.php
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password = null,
    ) {}
}

// In controller - validates and transforms automatically
public function store(UserData $data): JsonResponse
{
    $user = User::create($data->toArray());
    return $this->created(UserResource::make($user));
}
```

## Rate Limiting

Configured in `app/Providers/AppServiceProvider.php`:

| Limiter | Limit | Use Case |
|---------|-------|----------|
| `api` | 60/min | Default for all API routes |
| `auth` | 5/min | Login/register (brute force protection) |
| `authenticated` | 120/min | Logged-in users |

### Applying Rate Limiters

```php
// In routes/api.php
Route::middleware('throttle:auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware(['auth:api', 'throttle:authenticated'])->group(function () {
    // Protected routes with higher limits
});
```

### Rate Limit Headers

Responses include rate limit information:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
Retry-After: 60  # When limit exceeded
```

## Optional Packages

The following packages are **suggested** (not required) and can be installed individually to extend the kit's capabilities. They are fully opt-in and will not affect existing behavior.

### API Idempotency

[grazulex/laravel-api-idempotency](https://github.com/Grazulex/laravel-api-idempotency) provides RFC-compliant idempotency for your API endpoints. It prevents duplicate operations when clients retry requests (critical for payments, order creation, etc.).

**Install:**
```bash
composer require grazulex/laravel-api-idempotency
```

**Publish config (optional):**
```bash
php artisan vendor:publish --tag="api-idempotency-config"
```

**Usage — apply the middleware to mutation routes:**
```php
// routes/api/v1.php
Route::middleware(['auth:api', 'throttle:authenticated'])->group(function () {
    Route::post('orders', [OrderController::class, 'store'])
        ->middleware('idempotent');

    Route::post('payments', [PaymentController::class, 'store'])
        ->middleware('idempotent:required'); // Require Idempotency-Key header
});
```

**Client-side — include the `Idempotency-Key` header:**
```bash
curl -X POST http://localhost:8000/api/v1/orders \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..." \
  -H "Idempotency-Key: order_unique_key_123" \
  -H "Content-Type: application/json" \
  -d '{"product_id": 1, "quantity": 2}'
```

> **Attention:**
> - Only apply the `idempotent` middleware to mutation routes (POST, PUT, PATCH). GET requests are naturally idempotent.
> - The default storage driver is `cache`. For production with multiple servers, use the `redis` or `database` driver.
> - Keys are scoped per user by default. Two different users can use the same key without conflict.

---

### Smart Rate Limiting

[grazulex/laravel-api-throttle-smart](https://github.com/Grazulex/laravel-api-throttle-smart) provides plan-aware rate limiting with quotas, multiple algorithms (fixed window, sliding window, token bucket), and multi-tenant support. Ideal for SaaS APIs with subscription tiers.

**Install:**
```bash
composer require grazulex/laravel-api-throttle-smart
```

**Publish config:**
```bash
php artisan vendor:publish --tag="throttle-smart-config"
```

**Usage — apply to routes where plan-based limiting is needed:**
```php
// routes/api/v1.php
Route::middleware(['auth:api', 'throttle.smart'])->group(function () {
    Route::apiResource('posts', PostController::class);
});
```

> **Attention:**
> - This package **coexists** with Laravel's built-in `throttle:` middleware. You do not need to remove the existing rate limiters.
> - If you want to **replace** the native throttle on specific routes, swap `throttle:authenticated` with `throttle.smart` on those routes only.
> - Do **not** apply both `throttle:authenticated` and `throttle.smart` on the same route group — choose one per group to avoid double rate limiting.
> - The default driver is `cache`. For production, `redis` is recommended for performance and distributed consistency.
> - Configure your subscription plans in `config/throttle-smart.php` to match your business model (Free, Pro, Enterprise, etc.).

---

## Middleware

The kit includes three production-ready middleware patterns that you can apply to your routes as needed.

### Available Middleware

| Alias | Class | Description |
|-------|-------|-------------|
| `force.json` | `ForceJsonResponse` | Ensures all responses are JSON formatted |
| `log.api` | `LogApiRequests` | Logs API requests with timing information |
| `verified` | `EnsureEmailVerified` | Requires verified email to access route |

### ForceJsonResponse

Automatically sets `Accept: application/json` header and converts non-JSON responses to JSON format.

```php
Route::middleware('force.json')->group(function () {
    // All responses will be JSON
});
```

### LogApiRequests

Logs API requests with detailed information and adds `X-Response-Time` header to responses.

**Logged data:** timestamp, method, URL, IP, user ID, status code, duration (ms), user agent.

**Enable logging via environment:**
```env
APP_LOG_API_REQUESTS=true
```

```php
Route::middleware('log.api')->group(function () {
    // Requests will be logged
});
```

### EnsureEmailVerified

Protects routes that require a verified email address. Returns 403 if email is not verified.

```php
Route::middleware(['auth:api', 'verified'])->group(function () {
    // Only users with verified emails can access
});
```

**Response when email not verified:**
```json
{
  "success": false,
  "message": "Your email address is not verified. Please verify your email to continue."
}
```

## Email, Queues & Debugging

### Mailpit

In Docker, all mail goes to Mailpit: open [http://localhost:8025](http://localhost:8025) to read
verification and password-reset emails. Nothing leaves your machine.

### Queues

`VerifyEmailNotification` and `ResetPasswordNotification` implement `ShouldQueue`, so API responses
never wait on SMTP. Docker runs a `queue` service (`queue:work` on Redis) and a `scheduler` service
(`schedule:work`). Outside Docker, run `php artisan queue:work` yourself; the default
`QUEUE_CONNECTION=database` needs no extra services.

```bash
docker compose logs -f queue          # watch jobs being processed
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
```

Scheduled tasks (`routes/console.php`): `telescope:prune --hours=48` daily and `passport:purge` hourly.

### Telescope

[http://localhost:8000/telescope](http://localhost:8000/telescope) records requests, queries, jobs,
mail, notifications, cache, logs and exceptions.

- **local**: everything is recorded and the dashboard is open.
- **other environments**: only failures, exceptions and tagged entries are recorded; sensitive
  headers/parameters are hidden; the dashboard is restricted to emails in `TELESCOPE_ALLOWED_EMAILS`.
- Set `TELESCOPE_ENABLED=false` to turn it off completely (the production compose file does this by default).

Tests always run with Telescope disabled.

## AI Development (Laravel Boost)

[Laravel Boost](https://laravel.com/docs/boost) gives AI coding agents an MCP server with project-aware
tools (database schema, routes, logs, Tinker, version-specific docs search), plus guidelines and
skills for the packages in this kit (Passport, Socialite, Telescope, Pest, ...).

- `.mcp.json` registers the `laravel-boost` MCP server (`php artisan boost:mcp`) for Claude Code.
- `AGENTS.md` holds the generated guidelines; `boost.json` records which agents and skills are enabled.

After cloning, or after upgrading packages, regenerate guidelines and skills for your agent:

```bash
php artisan boost:install   # choose your agents (Claude Code, Cursor, Codex, ...)
php artisan boost:update    # refresh guidelines & skills
```

## Testing

This kit uses [Pest PHP](https://pestphp.com/) for testing:

```bash
# Run all tests
docker compose exec app ./vendor/bin/pest

# Run specific test file
docker compose exec app ./vendor/bin/pest tests/Feature/Api/V1/AuthTest.php

# Run with coverage
docker compose exec app ./vendor/bin/pest --coverage

# Run in parallel
docker compose exec app ./vendor/bin/pest --parallel
```

Tests always use in-memory SQLite, the `sync` queue and the `array` mailer (`phpunit.xml` forces these),
so running them inside the Docker containers never touches the MySQL development database.
`tests/TestCase.php` creates Passport keys and a personal access client for each test.

### Writing Tests

```php
// tests/Feature/Api/V1/UserTest.php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists users for authenticated user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->accessToken;

    User::factory()->count(5)->create();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/users');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'name', 'email']
            ]
        ]);
});

it('requires authentication', function () {
    $this->getJson('/api/v1/users')
        ->assertStatus(401);
});
```

## Code Quality

This kit includes strict code quality tools configured following [nunomaduro/laravel-starter-kit](https://github.com/nunomaduro/laravel-starter-kit) standards.

### Tools

| Tool | Purpose | Config |
|------|---------|--------|
| [PHPStan](https://phpstan.org/) + [Larastan](https://github.com/larastan/larastan) | Static analysis (level max) | `phpstan.neon` |
| [Rector](https://getrector.com/) | Automated refactoring | `rector.php` |
| [Pint](https://laravel.com/docs/pint) | Code style (strict rules) | `pint.json` |

### Composer Scripts

```bash
# Apply all fixes (Rector + Pint)
composer lint

# Check without fixing (CI mode)
composer test:lint

# Static analysis only
composer test:types

# Unit tests only
composer test:unit

# Full test suite (lint + types + unit)
composer test
```

### With Docker

```bash
docker compose exec app composer lint
docker compose exec app composer test
```

### Strict Rules Applied

- `declare(strict_types=1)` on all files
- `final` classes by default
- Type declarations enforced
- Dead code removal
- Early returns
- Strict comparisons

### GitHub Actions

Tests run automatically on push/PR to `main` via `.github/workflows/tests.yml`.

## Development Commands

```bash
# List all routes
docker compose exec app php artisan route:list

# Clear all caches
docker compose exec app php artisan optimize:clear

# Export OpenAPI spec to file
docker compose exec app php artisan scramble:export

# Ensure Passport keys + personal access client exist
docker compose exec app php artisan app:passport-setup

# Create an OAuth client (e.g. machine-to-machine)
docker compose exec app php artisan passport:client --client
```

## Docker

### Development (`docker-compose.yml`)

| Service | Purpose | Port |
|---------|---------|------|
| `app` | PHP 8.4-FPM; on boot runs `composer install` (if needed), migrations, `app:passport-setup` | - |
| `nginx` | Web server for the API | `APP_PORT` (8000) |
| `mysql` | MySQL 8.4 | `FORWARD_DB_PORT` (3306) |
| `redis` | Queue and cache | `FORWARD_REDIS_PORT` (6379) |
| `mailpit` | SMTP catcher + web UI | 1025 / 8025 |
| `queue` | `php artisan queue:work` | - |
| `scheduler` | `php artisan schedule:work` | - |

The source code is bind-mounted, so changes apply immediately (restart `queue` after changing job code:
`docker compose restart queue`). The compose file overrides DB, Redis, queue and mail settings, so the
same `.env` still works for running the app directly on your host with SQLite.

If your user ID is not 1000, build with `UID=$(id -u) docker compose up -d --build` so files created
in the container are owned by you.

### Production (`docker-compose.prod.yml`)

The `prod` image bakes the code in with `composer install --no-dev`, an authoritative classmap, and
OPcache (`docker/php/production.ini`). A separate `nginx` image serves `public/`.

```bash
# On the server, create .env with production values (APP_KEY, DB_*, DB_ROOT_PASSWORD, APP_URL, ...)
docker compose -f docker-compose.prod.yml up -d --build
```

On every start the entrypoint runs `php artisan optimize` (config/route/event/view caches). The `app`
container also runs `migrate --force` and `app:passport-setup`; the `queue` and `scheduler` containers
wait until `app` is healthy. The `storage` volume persists logs and the generated Passport keys.
Telescope is disabled unless you set `TELESCOPE_ENABLED=true`; Mailpit is not included, so configure a
real `MAIL_MAILER`. Put a TLS-terminating proxy or load balancer in front of `nginx`.

## Environment Configuration

Key `.env` variables:

```env
# Application
APP_NAME="Laravel API Kit"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Frontend that receives password-reset / verify-email links
FRONTEND_URL=http://localhost:3000

# Database (SQLite on the host; docker compose switches to MySQL automatically)
DB_CONNECTION=sqlite

# Mail (Mailpit)
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025

# Queue
QUEUE_CONNECTION=database

# Passport
PASSPORT_TOKENS_EXPIRE_IN_DAYS=15
PASSPORT_REFRESH_TOKENS_EXPIRE_IN_DAYS=30
PASSPORT_PERSONAL_ACCESS_TOKENS_EXPIRE_IN_MONTHS=6

# Telescope
TELESCOPE_ENABLED=true
TELESCOPE_ALLOWED_EMAILS=admin@example.com

# Socialite
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=

# API Versioning
API_VERSION_STRATEGY=uri
API_DEFAULT_VERSION=latest

# Rate Limiting
API_RATE_LIMIT=60

# Documentation
API_DOCS_URL=http://localhost:8000/docs/api
```

## Deployment

### Production Checklist

- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Configure proper database (MySQL/PostgreSQL)
- [ ] Set `APP_URL` to your production URL
- [ ] Set `FRONTEND_URL` so email links point to your client app
- [ ] Persist Passport keys (`storage` volume) or provide `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY`
- [ ] Disable Telescope (`TELESCOPE_ENABLED=false`) or restrict it with `TELESCOPE_ALLOWED_EMAILS`
- [ ] Configure a real mailer (Mailpit is for local only)
- [ ] Set Socialite redirect URIs to your production domain
- [ ] Review and tighten CORS settings in `config/cors.php`
- [ ] Set up proper rate limiting for production load
- [ ] Configure caching (Redis recommended)
- [ ] Run a queue worker and the scheduler (both included in `docker-compose.prod.yml`)
- [ ] Enable HTTPS and update URLs

### Docker Production

See [Docker > Production](#production-docker-composeprodyml).

## Extending the Kit

### Adding a New Resource (CRUD Example)

1. **Create Model & Migration:**
```bash
docker compose exec app php artisan make:model Post -m
```

2. **Create Controller:**
```php
// app/Http/Controllers/Api/V1/PostController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Spatie\QueryBuilder\QueryBuilder;

class PostController extends ApiController
{
    public function index()
    {
        $posts = QueryBuilder::for(Post::class)
            ->allowedFilters(['title', 'status'])
            ->allowedSorts(['title', 'created_at'])
            ->allowedIncludes(['author', 'comments'])
            ->paginate();

        return $this->success(PostResource::collection($posts));
    }

    public function show(Post $post)
    {
        return $this->success(new PostResource($post));
    }

    // ... store, update, destroy methods
}
```

3. **Create Resource:**
```php
// app/Http/Resources/PostResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'author' => new UserResource($this->whenLoaded('author')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

4. **Add Routes:**
```php
// routes/api/v1.php
Route::middleware('auth:api')->group(function () {
    // ... existing routes
    Route::apiResource('posts', PostController::class);
});
```

5. **Create Tests:**
```php
// tests/Feature/Api/V1/PostTest.php
uses(RefreshDatabase::class);

it('lists posts', function () {
    $user = User::factory()->create();
    Post::factory()->count(3)->create();

    Passport::actingAs($user);

    $this->getJson('/api/v1/posts')
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
});
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

- [Laravel](https://laravel.com) - The PHP Framework
- [Laravel Passport](https://laravel.com/docs/passport) - OAuth2 Server & API Authentication
- [Laravel Socialite](https://laravel.com/docs/socialite) - Social Login
- [Laravel Telescope](https://laravel.com/docs/telescope) - Debug Assistant
- [Laravel Boost](https://laravel.com/docs/boost) - AI Development Tooling
- [Mailpit](https://mailpit.axllent.org) - Local Email Testing
- [grazulex/laravel-apiroute](https://github.com/Grazulex/laravel-apiroute) - API Versioning
- [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder) - Query Building
- [spatie/laravel-data](https://github.com/spatie/laravel-data) - Data Transfer Objects
- [dedoc/scramble](https://github.com/dedoc/scramble) - API Documentation
- [grazulex/laravel-api-idempotency](https://github.com/Grazulex/laravel-api-idempotency) - API Idempotency (optional)
- [grazulex/laravel-api-throttle-smart](https://github.com/Grazulex/laravel-api-throttle-smart) - Smart Rate Limiting (optional)
- [Pest PHP](https://pestphp.com) - Testing Framework

## Support

- [Documentation](https://github.com/grazulex/laravel-api-kit/wiki)
- [Issues](https://github.com/grazulex/laravel-api-kit/issues)
- [Discussions](https://github.com/grazulex/laravel-api-kit/discussions)
