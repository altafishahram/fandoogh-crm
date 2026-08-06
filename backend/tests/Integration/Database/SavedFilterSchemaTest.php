<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use Illuminate\Support\Facades\Schema;
use Tests\Support\IdentityTestCase;

final class SavedFilterSchemaTest extends IdentityTestCase
{
    public function test_saved_filter_table_has_tenant_user_and_filter_columns(): void
    {
        self::assertTrue(Schema::hasColumns('saved_filters', [
            'agency_id', 'user_id', 'module', 'name', 'filters', 'sort', 'is_default',
        ]));
    }
}
