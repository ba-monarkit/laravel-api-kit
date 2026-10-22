<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\SocialCallbackRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as OAuth2User;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SocialAuthController extends ApiController
{
    /**
     * Get the OAuth provider authorization URL the client should redirect to.
     *
     * The provider sends the user back to the frontend, which then posts the
     * received `code` to the callback endpoint.
     *
     * @unauthenticated
     */
    public function redirect(string $provider): JsonResponse
    {
        return $this->success([
            'url' => $this->driver($provider)->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Exchange the provider authorization code for a Passport access token.
     *
     * @unauthenticated
     */
    public function callback(SocialCallbackRequest $request, string $provider): JsonResponse
    {
        try {
            $socialUser = $this->driver($provider)->user();
        } catch (Throwable) {
            return $this->unauthorized('Unable to authenticate with '.$provider);
        }

        $user = User::query()->firstWhere([
            'provider' => $provider,
            'provider_id' => (string) $socialUser->getId(),
        ]);

        if (! $user) {
            $email = $socialUser->getEmail();

            if (! is_string($email) || $email === '') {
                return $this->error('The '.$provider.' account has no email address.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $emailVerified = $this->providerVerifiedEmail($provider, $socialUser);
            $user = User::query()->firstWhere('email', $email);

            if ($user && ! $emailVerified) {
                // Linking on an unverified email would let anyone claim an existing account.
                return $this->error(
                    'An account with this email already exists. Log in with your password instead.',
                    Response::HTTP_CONFLICT,
                );
            }

            if ($user && ! $user->hasVerifiedEmail()) {
                // The local account never proved ownership of the email, so whoever set its
                // password may not own it: drop that password and its tokens before linking.
                $user->password = null;
                $user->tokens()->update(['revoked' => true]);
            }

            $user ??= new User(['email' => $email]);

            if ($emailVerified && ! $user->hasVerifiedEmail()) {
                $user->email_verified_at = now();
            }
        }

        $user->forceFill([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $user->email,
            'provider' => $provider,
            'provider_id' => (string) $socialUser->getId(),
            'avatar' => $socialUser->getAvatar(),
        ])->save();

        return $this->success([
            'user' => new UserResource($user),
            'token' => $user->createToken('auth-token')->accessToken,
        ], 'Login successful');
    }

    /**
     * Whether the provider guarantees the user owns the returned email.
     */
    private function providerVerifiedEmail(string $provider, SocialUser $socialUser): bool
    {
        $raw = $socialUser instanceof OAuth2User ? $socialUser->getRaw() : [];

        return match ($provider) {
            // With the default user:email scope Socialite only returns the primary, verified email.
            'github' => true,
            'google' => ($raw['email_verified'] ?? false) === true,
            default => false,
        };
    }

    private function driver(string $provider): AbstractProvider
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        return $driver->stateless();
    }
}
