<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:passport-setup', function (ClientRepository $clients): void {
    $keysFromEnv = filled(config('passport.private_key')) && filled(config('passport.public_key'));

    if (! $keysFromEnv && ! file_exists(Passport::keyPath('oauth-private.key'))) {
        $this->call('passport:keys', ['--no-interaction' => true]);
    }

    try {
        $clients->personalAccessClient('users');
        $this->info('Personal access client already exists.');
    } catch (RuntimeException) {
        $clients->createPersonalAccessGrantClient(config()->string('app.name'), 'users');
        $this->info('Personal access client created.');
    }
})->purpose('Ensure Passport keys and the personal access client exist (idempotent)');

// Housekeeping: keep the Telescope and OAuth token tables from growing forever.
Schedule::command('telescope:prune --hours=48')->daily();
Schedule::command('passport:purge')->hourly();
