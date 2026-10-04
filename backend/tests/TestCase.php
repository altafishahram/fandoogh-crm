<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\SafeTestEnvironment;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $application = parent::createApplication();
        $intended = getenv('DB_DATABASE');
        if (! is_string($intended)) {
            throw new \LogicException('DB_DATABASE must name an isolated test schema.');
        }
        SafeTestEnvironment::assert($application, $intended);

        return $application;
    }

    /** @return void */
    protected function beforeRefreshingDatabase()
    {
        SafeTestEnvironment::assert($this->app, (string) getenv('DB_DATABASE'));
    }
}
