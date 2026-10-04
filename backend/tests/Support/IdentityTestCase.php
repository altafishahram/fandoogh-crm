<?php

declare(strict_types=1);

namespace Tests\Support;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class IdentityTestCase extends TestCase
{
    use RefreshDatabase;

    /** @return void */
    protected function beforeRefreshingDatabase()
    {
        SafeTestEnvironment::assert($this->app, (string) getenv('DB_DATABASE'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }
}
