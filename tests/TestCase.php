<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! file_exists(Passport::keyPath('oauth-private.key'))) {
            Artisan::call('passport:keys', ['--no-interaction' => true]);
        }

        // Personal access tokens require a personal access client in the database.
        if (Schema::hasTable('oauth_clients')) {
            resolve(ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client', 'users');
        }
    }
}
