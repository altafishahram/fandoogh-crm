<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class IranLocationsSeeder extends Seeder
{
    public function run(): void
    {
        $source = file_get_contents(database_path('data/iran-1404.json'));
        if ($source === false) {
            throw new \RuntimeException('Iran geography snapshot is missing.');
        }
        $data = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($data): void {
            foreach (['provinces', 'counties', 'cities'] as $level) {
                foreach (array_chunk($data[$level], 200) as $rows) {
                    DB::table('location_'.$level)->upsert($rows, ['id'], array_keys($rows[0]));
                }
            }
        });
    }
}
