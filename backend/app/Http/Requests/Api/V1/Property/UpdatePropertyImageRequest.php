<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Property;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePropertyImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sort_order' => ['required', 'integer', 'between:0,19'],
            'is_cover' => ['required', 'boolean'],
            'expected_updated_at' => ['required', 'date'],
        ];
    }
}
