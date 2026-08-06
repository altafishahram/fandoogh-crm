<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Application\Owner\Data\OwnerData;
use App\Application\Owner\Services\CreateOwnerService;
use App\Domain\Owner\Enums\OwnerType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\DomainTestCase;

final class CoreDomainSchemaTest extends DomainTestCase
{
    public function test_core_domain_tables_and_critical_columns_exist(): void
    {
        foreach (['owners', 'properties', 'property_owner', 'property_images', 'property_notes',
            'property_histories', 'customers', 'customer_notes'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' is missing.');
        }

        $this->assertTrue(Schema::hasColumns('properties', [
            'agency_id', 'code', 'transaction_type', 'status', 'currency_code', 'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumns('customers', [
            'agency_id', 'assigned_agent_id', 'intent', 'status', 'converted_property_id',
        ]));
    }

    public function test_owner_identity_number_is_encrypted_at_rest(): void
    {
        ['manager' => $manager] = $this->tenant();
        $this->establish($manager);
        $owner = app(CreateOwnerService::class)->execute($manager, new OwnerData(
            OwnerType::Person, 'Ali', 'Ahmadi', null, '+989121234567', null, null,
            '0012345678', null, null, null, null, null, null,
        ));

        $raw = DB::table('owners')->where('id', $owner->getKey())->value('identity_number_encrypted');
        $this->assertIsString($raw);
        $this->assertNotSame('0012345678', $raw);
        $this->assertSame('0012345678', $owner->refresh()->identity_number_encrypted);
    }
}
