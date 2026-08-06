<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IdentityTestCase;

final class IdentitySchemaTest extends IdentityTestCase
{
    public function test_identity_tables_and_critical_columns_exist(): void
    {
        foreach ([
            'agencies',
            'agency_settings',
            'users',
            'roles',
            'permissions',
            'model_has_roles',
            'role_has_permissions',
            'model_has_permissions',
            'sessions',
            'personal_access_tokens',
        ] as $table) {
            self::assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        self::assertTrue(Schema::hasColumns('users', [
            'agency_id',
            'is_active',
            'must_change_password',
            'last_login_at',
            'deleted_at',
        ]));
        self::assertFalse(Schema::hasColumn('users', 'role'));
        self::assertFalse(Schema::hasColumn('users', 'permissions'));
    }

    public function test_database_constraints_reject_invalid_agency_settings(): void
    {
        $agencyId = DB::table('agencies')->insertGetId([
            'name' => 'Constraint Test',
            'slug' => 'constraint-test',
            'email' => 'constraint@example.test',
            'phone' => '+982100000000',
            'address_line_1' => 'Test',
            'city' => 'Tehran',
            'province' => 'Tehran',
            'country_code' => 'IR',
            'timezone' => 'Asia/Tehran',
            'locale' => 'fa-IR',
            'currency_code' => 'IRR',
            'is_active' => false,
        ]);

        $this->expectException(QueryException::class);
        DB::table('agency_settings')->insert([
            'agency_id' => $agencyId,
            'property_code_prefix' => 'x',
            'next_property_sequence' => 0,
            'default_page_size' => 17,
        ]);
    }
}
