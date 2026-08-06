<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Property;

use App\Application\Property\Data\ChangePropertyStatusData;
use App\Domain\Property\Enums\PropertyStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangePropertyStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PropertyStatus::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
            'closed_at' => ['nullable', 'date'],
            'expected_updated_at' => ['required', 'date'],
        ];
    }

    public function toData(): ChangePropertyStatusData
    {
        $data = $this->validated();

        return new ChangePropertyStatusData(
            PropertyStatus::from($data['status']),
            $data['reason'] ?? null,
            isset($data['closed_at']) ? CarbonImmutable::parse($data['closed_at']) : null,
            CarbonImmutable::parse($data['expected_updated_at']),
        );
    }
}
