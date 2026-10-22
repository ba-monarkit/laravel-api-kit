<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Throwable;

final class SocialAuthController extends ApiController
{
    /**
     * Get the OAuth provider authorization URL the client should redirect to.
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
     * Handle the provider callback and issue a Passport access token.
     *
     * @unauthenticated
     */
    public function callback(string $provider): JsonResponse
    {
        try {
            $socialUser = $this->driver($provider)->user();
        } catch (Throwable) {
            return $this->unauthorized('Unable to authenticate with '.$provider);
        }

        $user = User::query()->firstWhere([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
        ]) ?? User::query()->firstWhere('email', $socialUser->getEmail());

        if (! $user) {
            $user = new User;
            $user->email = (string) $socialUser->getEmail();
            $user->email_verified_at = now();
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

    private function driver(string $provider): AbstractProvider
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        return $driver->stateless();
    }
}
