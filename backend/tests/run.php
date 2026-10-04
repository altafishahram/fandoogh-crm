<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;
use Tests\Support\SafeTestEnvironment;

// Composer's runner chooses a dedicated local test schema explicitly. Direct
// PHPUnit invocations remain protected by tests/bootstrap.php and TestCase.
$intendedDatabase = getenv('FANDOOGH_TEST_DATABASE') ?: 'fandoogh_test';
if (! str_ends_with($intendedDatabase, '_test')) {
    throw new LogicException('FANDOOGH_TEST_DATABASE must end in _test.');
}
putenv('FANDOOGH_TEST_DATABASE='.$intendedDatabase);
putenv('DB_DATABASE='.$intendedDatabase);
require __DIR__.'/bootstrap.php';
$testApplication = require dirname(__DIR__).'/bootstrap/app.php';
$testApplication->make(Kernel::class)->bootstrap();
SafeTestEnvironment::assert($testApplication, $intendedDatabase);
if (in_array('--preflight', $argv, true)) {
    $testConnection = $testApplication['config']->get('database.connections.'.$testApplication['config']->get('database.default'));
    $actual = $testApplication->make('db')->connection()->selectOne('SELECT DATABASE() AS test_database');
    echo json_encode(['environment' => $testApplication->environment(), 'database' => $testConnection['database'], 'actual_database' => $actual->test_database, 'host' => $testConnection['host'], 'driver' => $testConnection['driver'], 'url_disabled' => empty($testConnection['url']), 'socket_disabled' => empty($testConnection['unix_socket']), 'cached' => $testApplication->configurationIsCached()], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}
$testProcess = new Process([PHP_BINARY, dirname(__DIR__).'/vendor/bin/phpunit', ...array_slice($argv, 1)], dirname(__DIR__));
$testProcess->setTimeout(null);
exit($testProcess->run(static function (string $type, string $output): void {
    echo $output;
}));
