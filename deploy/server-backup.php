<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

$backend = $argv[1] ?? null;
$outputDirectory = $argv[2] ?? null;
if (! is_string($backend) || ! is_dir($backend) || ! is_string($outputDirectory)) {
    fwrite(STDERR, "Usage: php server-backup.php BACKEND_PATH OUTPUT_DIRECTORY\n");
    exit(1);
}

require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var array<string, mixed> $database */
$database = config('database.connections.mysql');
foreach (['host', 'port', 'database', 'username', 'password'] as $required) {
    if (! isset($database[$required]) || ! is_scalar($database[$required])) {
        fwrite(STDERR, "The production database configuration is incomplete.\n");
        exit(1);
    }
}

if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0700, true) && ! is_dir($outputDirectory)) {
    fwrite(STDERR, "The backup directory could not be created.\n");
    exit(1);
}
chmod($outputDirectory, 0700);

$optionFile = tempnam(sys_get_temp_dir(), 'melkban-db-');
if ($optionFile === false) {
    fwrite(STDERR, "A protected temporary option file could not be created.\n");
    exit(1);
}

$escape = static fn (string $value): string => str_replace(
    ["\\", '"', "\n", "\r"],
    ["\\\\", '\\"', '', ''],
    $value,
);
$options = sprintf(
    "[client]\nhost=\"%s\"\nport=\"%s\"\nuser=\"%s\"\npassword=\"%s\"\n",
    $escape((string) $database['host']),
    $escape((string) $database['port']),
    $escape((string) $database['username']),
    $escape((string) $database['password']),
);
file_put_contents($optionFile, $options, LOCK_EX);
chmod($optionFile, 0600);

$stamp = gmdate('YmdHis');
$sqlPath = rtrim($outputDirectory, '/').'/melkban-before-'.$stamp.'.sql';
$gzipPath = $sqlPath.'.gz';

try {
    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', $sqlPath, 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([
        '/usr/bin/mysqldump',
        '--defaults-extra-file='.$optionFile,
        '--single-transaction',
        '--routines',
        '--triggers',
        '--set-gtid-purged=OFF',
        '--no-tablespaces',
        (string) $database['database'],
    ], $descriptors, $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('mysqldump could not be started.');
    }
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('mysqldump failed: '.trim($error));
    }
    chmod($sqlPath, 0600);

    $gzip = proc_open(
        ['/usr/bin/gzip', '-9', '--', $sqlPath],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']],
        $gzipPipes,
    );
    if (! is_resource($gzip)) {
        throw new RuntimeException('gzip could not be started.');
    }
    $gzipError = stream_get_contents($gzipPipes[2]);
    fclose($gzipPipes[2]);
    if (proc_close($gzip) !== 0) {
        throw new RuntimeException('gzip failed: '.trim($gzipError));
    }
    chmod($gzipPath, 0600);

    echo json_encode([
        'path' => $gzipPath,
        'bytes' => filesize($gzipPath),
        'sha256' => hash_file('sha256', $gzipPath),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $exception) {
    @unlink($sqlPath);
    @unlink($gzipPath);
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
} finally {
    @unlink($optionFile);
}
