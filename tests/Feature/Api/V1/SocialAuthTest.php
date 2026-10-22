<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $raw
 */
function fakeSocialiteUser(string $provider = 'github', ?string $email = 'octocat@example.com', array $raw = []): void
{
    $socialUser = (new SocialiteUser)->setRaw($raw)->map([
        'id' => '12345',
        'name' => 'Octo Cat',
        'nickname' => 'octocat',
        'email' => $email,
        'avatar' => 'https://example.com/avatar.png',
    ]);

    $driver = Mockery::mock($provider === 'google' ? GoogleProvider::class : GithubProvider::class);
    $driver->shouldReceive('stateless')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($socialUser);

    Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
}

function socialCallback(string $provider = 'github'): TestResponse
{
    return test()->postJson('/api/v1/auth/'.$provider.'/callback', ['code' => 'auth-code']);
}

it('returns the provider redirect url pointing back to the frontend', function (): void {
    config()->set('services.github.client_id', 'client-id');
    config()->set('services.github.redirect', 'https://app.example.com/auth/github/callback');

    $this->getJson('/api/v1/auth/github/redirect')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.url', fn (string $url): bool => str_contains($url, urlencode('https://app.example.com/auth/github/callback')));
});

it('rejects unsupported providers', function (): void {
    $this->getJson('/api/v1/auth/myspace/redirect')->assertNotFound();
});

it('does not accept the callback as a browser GET request', function (): void {
    $this->getJson('/api/v1/auth/github/callback?code=auth-code')->assertMethodNotAllowed();
});

it('requires the authorization code', function (): void {
    $this->postJson('/api/v1/auth/github/callback')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

it('creates a verified user and issues a token on callback', function (): void {
    fakeSocialiteUser();

    socialCallback()
        ->assertOk()
        ->assertJsonStructure(['data' => ['user' => ['id', 'email', 'avatar'], 'token']]);

    $user = User::query()->where('email', 'octocat@example.com')->firstOrFail();

    expect($user->provider)->toBe('github')
        ->and($user->provider_id)->toBe('12345')
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

it('logs in a returning social user by provider id', function (): void {
    User::factory()->create([
        'email' => 'old@example.com',
        'provider' => 'github',
        'provider_id' => '12345',
    ]);
    fakeSocialiteUser(email: 'new@example.com');

    socialCallback()->assertOk()->assertJsonPath('data.user.email', 'old@example.com');

    expect(User::query()->count())->toBe(1);
});

it('links the provider to an existing verified user with the same verified email', function (): void {
    $existing = User::factory()->create(['email' => 'octocat@example.com']);
    fakeSocialiteUser();

    socialCallback()->assertOk();

    $existing->refresh();

    expect(User::query()->count())->toBe(1)
        ->and($existing->provider_id)->toBe('12345')
        ->and($existing->password)->not->toBeNull();
});

it('refuses to link an existing account when the provider email is not verified', function (): void {
    $existing = User::factory()->create(['email' => 'victim@example.com']);
    fakeSocialiteUser('google', 'victim@example.com', ['email_verified' => false]);

    socialCallback('google')
        ->assertStatus(409)
        ->assertJsonPath('success', false);

    expect($existing->fresh()?->provider)->toBeNull();
});

it('links a google account only when google verified the email', function (): void {
    $existing = User::factory()->create(['email' => 'person@example.com']);
    fakeSocialiteUser('google', 'person@example.com', ['email_verified' => true]);

    socialCallback('google')->assertOk();

    expect($existing->fresh()?->provider)->toBe('google');
});

it('creates an unverified user when the provider email is not verified', function (): void {
    fakeSocialiteUser('google', 'fresh@example.com', ['email_verified' => false]);

    socialCallback('google')->assertOk();

    expect(User::query()->where('email', 'fresh@example.com')->firstOrFail()->hasVerifiedEmail())->toBeFalse();
});

it('wipes the password and tokens of an unverified pre-registered account when linking', function (): void {
    // An attacker registered first with the victim's email but could never verify it.
    $squatter = User::factory()->create([
        'email' => 'octocat@example.com',
        'email_verified_at' => null,
    ]);
    $squatter->createToken('attacker');
    fakeSocialiteUser();

    socialCallback()->assertOk();

    $squatter->refresh();

    expect($squatter->password)->toBeNull()
        ->and($squatter->hasVerifiedEmail())->toBeTrue();

    $this->assertDatabaseMissing('oauth_access_tokens', [
        'user_id' => $squatter->id,
        'name' => 'attacker',
        'revoked' => false,
    ]);

    $this->postJson('/api/v1/login', ['email' => 'octocat@example.com', 'password' => 'password'])
        ->assertUnauthorized();
});

it('rejects provider accounts without an email', function (): void {
    fakeSocialiteUser(email: null);

    socialCallback()->assertUnprocessable();

    expect(User::query()->count())->toBe(0);
});
