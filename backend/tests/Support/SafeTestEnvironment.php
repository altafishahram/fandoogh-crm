<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Application;
use LogicException;

final class SafeTestEnvironment
{
    public static function assert(Application $application, string $intendedDatabase): void
    {
        $default = $application['config']->get('database.default');
        $connection = $application['config']->get('database.connections.'.$default);
        if ($application->environment() !== 'testing' || ! str_ends_with($intendedDatabase, '_test')
            || ! is_array($connection) || ($connection['database'] ?? null) !== $intendedDatabase
            || ! in_array($connection['host'] ?? null, ['db', 'localhost', '127.0.0.1'], true)
            || ! empty($connection['url']) || ! empty($connection['unix_socket']) || ! in_array($default, ['mysql', 'mariadb'], true)) {
            throw new LogicException('Refusing tests: effective Laravel environment, host, URL and database must match the isolated local test connection.');
        }
        $actual = $application->make('db')->connection()->selectOne('SELECT DATABASE() AS test_database');
        if (($actual->test_database ?? null) !== $intendedDatabase) {
            throw new LogicException('Refusing tests: SELECT DATABASE() does not match the intended isolated test schema.');
        }
    }
}
