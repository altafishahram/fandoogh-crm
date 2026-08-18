<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE customers DROP CHECK chk_customers_status');
        DB::statement("ALTER TABLE customers MODIFY status VARCHAR(32) NOT NULL DEFAULT 'active'");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_status CHECK (status IN ('active','finalized','withdrawn','transacted_elsewhere','inactive','converted','lost'))");
    }

    public function down(): void
    {
        if (DB::table('customers')->whereRaw('CHAR_LENGTH(status) > 16')->exists()) {
            throw new RuntimeException('بازگشت طول وضعیت مشتری با داده‌های فعلی امن نیست.');
        }

        DB::statement('ALTER TABLE customers DROP CHECK chk_customers_status');
        DB::statement("ALTER TABLE customers MODIFY status VARCHAR(16) NOT NULL DEFAULT 'active'");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_status CHECK (status IN ('active','finalized','withdrawn','transacted_elsewhere','inactive','converted','lost'))");
    }
};
