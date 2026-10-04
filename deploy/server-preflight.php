<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$backend = $argv[1] ?? null;
if (! is_string($backend) || ! is_dir($backend)) {
    fwrite(STDERR, "Usage: php server-preflight.php BACKEND_PATH\n");
    exit(1);
}

require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$multipleOwners = DB::query()->fromSub(
    DB::table('property_owner')
        ->selectRaw('agency_id, property_id, COUNT(*) AS aggregate')
        ->groupBy('agency_id', 'property_id')
        ->havingRaw('COUNT(*) > 1'),
    'duplicates',
)->count();
$reusedOwners = DB::query()->fromSub(
    DB::table('property_owner')
        ->selectRaw('agency_id, owner_id, COUNT(*) AS aggregate')
        ->groupBy('agency_id', 'owner_id')
        ->havingRaw('COUNT(*) > 1'),
    'duplicates',
)->count();

echo json_encode([
    'properties_with_multiple_owners' => $multipleOwners,
    'owners_reused' => $reusedOwners,
], JSON_THROW_ON_ERROR).PHP_EOL;

exit(($multipleOwners === 0 && $reusedOwners === 0) ? 0 : 2);
