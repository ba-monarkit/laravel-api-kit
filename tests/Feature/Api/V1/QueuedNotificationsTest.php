<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('queues the verification email on registration', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/register', [
        'name' => 'Test User',
        'email' => 'queued@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $user = User::query()->where('email', 'queued@example.com')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmailNotification::class);
    expect(new VerifyEmailNotification)->toBeInstanceOf(ShouldQueue::class);
});

it('queues the password reset email', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson('/api/v1/forgot-password', ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
    expect(new ResetPasswordNotification('token'))->toBeInstanceOf(ShouldQueue::class);
});

it('points the password reset link to the frontend', function (): void {
    config()->set('app.frontend_url', 'https://app.example.com');
    $user = User::factory()->create(['email' => 'reset@example.com']);

    $mail = new ResetPasswordNotification('abc123')->toMail($user);

    expect($mail->actionUrl)->toBe('https://app.example.com/reset-password?token=abc123&email=reset%40example.com');
});

it('points the verification link to the frontend with a signed api url', function (): void {
    config()->set('app.frontend_url', 'https://app.example.com');
    $user = User::factory()->create(['email_verified_at' => null]);

    $mail = (new VerifyEmailNotification)->toMail($user);

    expect($mail->actionUrl)->toStartWith('https://app.example.com/verify-email?url=');

    parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);

    expect($query['url'])->toContain('/api/v1/email/verify/'.$user->id.'/')
        ->and($query['url'])->toContain('signature=');
});
