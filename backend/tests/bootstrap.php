<?php

declare(strict_types=1);

// This executes before Laravel or PHPUnit database traits. Container environment
// variables may win over PHPUnit's XML env entries, so getenv() is authoritative.
$testDatabase = getenv('FANDOOGH_TEST_DATABASE') ?: getenv('DB_DATABASE');
if (! is_string($testDatabase) || ! str_ends_with($testDatabase, '_test')) {
    throw new LogicException('Refusing database tests: explicitly set DB_DATABASE to a dedicated name ending in _test.');
}
$testHost = getenv('DB_HOST') ?: 'db';
if (! in_array($testHost, ['db', 'localhost', '127.0.0.1'], true)) {
    throw new LogicException('Refusing database tests against a non-local database host.');
}
$testCache = sys_get_temp_dir().'/fandoogh-phpunit-'.getmypid().'-config.php';
if (file_exists($testCache)) {
    throw new LogicException('The isolated test configuration cache must not exist.');
}
foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $testCache, 'DB_DATABASE' => $testDatabase, 'DB_HOST' => $testHost, 'DB_URL' => '', 'DB_SOCKET' => '', 'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $testKey => $testValue) {
    putenv($testKey.'='.$testValue);
    $_ENV[$testKey] = $testValue;
    $_SERVER[$testKey] = $testValue;
}
require_once dirname(__DIR__).'/vendor/autoload.php';
