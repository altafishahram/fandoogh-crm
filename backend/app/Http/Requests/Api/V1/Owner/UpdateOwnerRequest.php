<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Owner;

use App\Application\Owner\Data\OwnerData;
use App\Domain\Owner\Enums\OwnerType;
use App\Models\Owner;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

final class UpdateOwnerRequest extends StoreOwnerRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as $field => &$fieldRules) {
            if (! in_array($field, ['agency_id', 'created_by_user_id'], true)) {
                array_unshift($fieldRules, 'sometimes');
            }
        }
        unset($fieldRules);
        $rules['owner_type'] = ['sometimes', Rule::enum(OwnerType::class)];
        $rules['expected_updated_at'] = ['required', 'date'];

        return $rules;
    }

    public function toDataFor(Owner $owner): OwnerData
    {
        $data = array_merge([
            'owner_type' => $owner->owner_type->value,
            'first_name' => $owner->first_name,
            'last_name' => $owner->last_name,
            'company_name' => $owner->company_name,
            'mobile' => $owner->mobile,
            'phone' => $owner->phone,
            'email' => $owner->email,
            'identity_number' => $owner->identity_number_encrypted,
            'address_line_1' => $owner->address_line_1,
            'address_line_2' => $owner->address_line_2,
            'city' => $owner->city,
            'province' => $owner->province,
            'postal_code' => $owner->postal_code,
            'notes' => $owner->notes,
        ], $this->validated());

        return new OwnerData(
            OwnerType::from($data['owner_type']),
            $data['first_name'], $data['last_name'], $data['company_name'],
            $data['mobile'], $data['phone'], $data['email'], $data['identity_number'],
            $data['address_line_1'], $data['address_line_2'], $data['city'], $data['province'],
            $data['postal_code'], $data['notes'],
        );
    }

    public function expectedUpdatedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('expected_updated_at'));
    }
}
