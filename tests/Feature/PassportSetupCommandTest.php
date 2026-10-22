<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;

uses(RefreshDatabase::class);

it('creates the personal access client only once', function (): void {
    Client::query()->delete();

    $this->artisan('app:passport-setup')->assertSuccessful();
    $this->artisan('app:passport-setup')->assertSuccessful();

    $personalClients = Client::query()->get()
        ->filter(fn (Client $client): bool => $client->hasGrantType('personal_access'));

    expect($personalClients)->toHaveCount(1);
});
