<?php

declare(strict_types=1);

namespace App\Domain\Property\Enums;

enum DeliveryStatus: string
{
    case OwnerOccupied = 'owner_occupied';
    case TenantOccupied = 'tenant_occupied';
    case Ready = 'ready';
    case Vacated = 'vacated';
    /** وضعیت قدیمی اجاره که برای حفظ داده‌های قبلی باقی مانده است. */
    case Dated = 'dated';
}
