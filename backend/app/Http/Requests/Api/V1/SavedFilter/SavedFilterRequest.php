<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SavedFilter;

use App\Application\SavedFilter\Data\SavedFilterData;
use App\Domain\SavedFilter\Enums\FilterModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SavedFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'agency_id' => ['prohibited'], 'user_id' => ['prohibited'],
            'module' => ['required', Rule::enum(FilterModule::class)],
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'filters' => ['required', 'array'],
            'sort' => ['nullable', 'string', 'max:64'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): SavedFilterData
    {
        $data = $this->validated();

        return new SavedFilterData(
            FilterModule::from($data['module']), trim($data['name']), $data['filters'],
            $data['sort'] ?? null, (bool) ($data['is_default'] ?? false),
        );
    }
}
