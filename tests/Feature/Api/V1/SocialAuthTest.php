<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

function fakeSocialiteUser(string $email = 'octocat@example.com'): void
{
    $socialUser = (new SocialiteUser)->map([
        'id' => '12345',
        'name' => 'Octo Cat',
        'nickname' => 'octocat',
        'email' => $email,
        'avatar' => 'https://example.com/avatar.png',
    ]);

    $provider = Mockery::mock(GithubProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($socialUser);

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
}

it('returns the provider redirect url', function (): void {
    config()->set('services.github.client_id', 'client-id');

    $this->getJson('/api/v1/auth/github/redirect')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['url']]);
});

it('rejects unsupported providers', function (): void {
    $this->getJson('/api/v1/auth/myspace/redirect')->assertNotFound();
});

it('creates a verified user and issues a token on callback', function (): void {
    fakeSocialiteUser();

    $this->getJson('/api/v1/auth/github/callback')
        ->assertOk()
        ->assertJsonStructure(['data' => ['user' => ['id', 'email', 'avatar'], 'token']]);

    $user = User::query()->where('email', 'octocat@example.com')->firstOrFail();

    expect($user->provider)->toBe('github')
        ->and($user->provider_id)->toBe('12345')
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

it('links the provider to an existing user with the same email', function (): void {
    $existing = User::factory()->create(['email' => 'octocat@example.com']);
    fakeSocialiteUser();

    $this->getJson('/api/v1/auth/github/callback')->assertOk();

    expect(User::query()->count())->toBe(1)
        ->and($existing->fresh()?->provider_id)->toBe('12345');
});
