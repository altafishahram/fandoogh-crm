<?php

declare(strict_types=1);

namespace App\Application\Marketplace\Services;

use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Property;
use App\Models\PropertyPublication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublicationService
{
    public function canPublish(User $user): bool
    {
        $agency = $user->agency;

        return $user->is_active && ! $user->trashed() && $agency !== null && $agency->is_active && $agency->is_verified && $user->can(PermissionName::PropertiesPublish->value);
    }

    /** @param array<string, mixed> $data */
    public function save(User $actor, Property $property, array $data): PropertyPublication
    {
        abort_unless($actor->agency_id === $property->agency_id && $this->canPublish($actor), 403);

        return DB::transaction(function () use ($actor, $property, $data): PropertyPublication {
            /** @var Property $property */
            $property = Property::query()->lockForUpdate()->findOrFail((int) $property->getKey());
            if (($data['share_with_agencies'] || $data['publish_public']) && ($property->status !== PropertyStatus::Available || $property->city_id === null)) {
                throw ValidationException::withMessages(['publish_public' => ['فقط ملک موجود با موقعیت معتبر قابل انتشار است.']]);
            }
            $publication = PropertyPublication::query()->where('property_id', $property->getKey())->lockForUpdate()->first();
            if ($publication === null && isset($data['expected_version']) && (int) $data['expected_version'] !== 0) {
                throw new DomainConflictException('تنظیمات انتشار هنوز ایجاد نشده است.');
            }
            if ($publication !== null && isset($data['expected_version']) && $publication->version !== (int) $data['expected_version']) {
                throw new DomainConflictException('اطلاعات انتشار هم‌زمان تغییر کرده است.');
            }
            $responder = (int) ($data['responding_user_id'] ?? $publication->responding_user_id ?? $property->created_by_user_id);
            if ($publication !== null && $responder !== $publication->responding_user_id && $actor->roleName() !== RoleName::AgencyManager) {
                abort(403);
            }
            if ($publication === null && $responder !== (int) $property->created_by_user_id && $actor->roleName() !== RoleName::AgencyManager) {
                abort(403);
            }
            if (! User::query()->whereKey($responder)->where('agency_id', $property->agency_id)->where('is_active', true)->whereHas('roles', fn ($query) => $query->whereIn('name', [RoleName::AgencyManager->value, RoleName::Agent->value]))->exists()) {
                throw ValidationException::withMessages(['responding_user_id' => ['مشاور پاسخ‌گو باید عضو فعال همین آژانس باشد.']]);
            }
            $imageIds = array_values(array_unique($data['image_ids']));
            $data['public_title'] = trim(strip_tags((string) $data['public_title']));
            $data['public_description'] = isset($data['public_description']) ? trim(strip_tags((string) $data['public_description'])) : null;
            $this->assertSafeCopy($property, $data['public_title'].' '.($data['public_description'] ?? ''));
            if ($property->images()->whereIn('id', $imageIds)->count() !== count($imageIds)) {
                throw ValidationException::withMessages(['image_ids' => ['تصاویر انتخاب‌شده باید متعلق به همین ملک باشند.']]);
            }
            $publication ??= new PropertyPublication(['property_id' => $property->getKey(), 'agency_id' => $property->agency_id, 'version' => 0]);
            $publication->fill(array_intersect_key($data, array_flip(['share_with_agencies', 'publish_public', 'public_title', 'public_description'])));
            $publication->responding_user_id = $responder;
            $publication->version++;
            if ($data['share_with_agencies'] || $data['publish_public']) {
                $publication->published_at = CarbonImmutable::now();
            }
            $publication->save();
            $publication->images()->sync(array_combine($imageIds, array_map(fn ($order) => ['sort_order' => $order], array_keys($imageIds))) ?: []);

            return $publication->refresh();
        });
    }

    private function assertSafeCopy(Property $property, string $copy): void
    {
        $copy = $this->normalizeCopy($copy);
        $secrets = [$property->street_address];
        $numbers = [$property->postal_code];
        foreach ([$property->latitude, $property->longitude] as $coordinate) {
            if (is_string($coordinate)) {
                $secrets[] = rtrim(rtrim($coordinate, '0'), '.');
            }
        }
        foreach ($property->owners as $owner) {
            $secrets[] = $owner->full_name;
            $secrets[] = trim(($owner->first_name ?? '').' '.($owner->last_name ?? ''));
            $secrets[] = $owner->company_name;
            $secrets[] = $owner->address_line_1;
            $secrets[] = $owner->address_line_2;
            $numbers[] = $owner->mobile;
            $numbers[] = $owner->phone;
            $numbers[] = $owner->postal_code;
            $secrets[] = $owner->email;
        }
        foreach ($secrets as $secret) {
            if (is_string($secret) && mb_strlen(trim($secret)) >= 2 && mb_stripos($copy, $this->normalizeCopy(trim($secret))) !== false) {
                throw ValidationException::withMessages(['public_description' => ['متن آگهی نباید نام و تماس مالک یا نشانی دقیق ملک را شامل شود.']]);
            }
        }
        $copyDigits = preg_replace('/[^0-9]/u', '', $copy) ?? '';
        foreach ($numbers as $secret) {
            if (! is_string($secret)) {
                continue;
            }
            $digits = preg_replace('/[^0-9]/u', '', $this->normalizeCopy($secret)) ?? '';
            // The final ten digits also identify an Iranian mobile written with +98.
            $digits = strlen($digits) > 10 ? substr($digits, -10) : $digits;
            if (strlen($digits) >= 7 && str_contains($copyDigits, $digits)) {
                throw ValidationException::withMessages(['public_description' => ['متن آگهی نباید شماره مالک یا کد پستی ملک را شامل شود.']]);
            }
        }
    }

    private function normalizeCopy(string $value): string
    {
        $value = strtr($value, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.']);

        return preg_replace('/[\s\x{200C}\x{200D}]+/u', ' ', $value) ?? $value;
    }
}
