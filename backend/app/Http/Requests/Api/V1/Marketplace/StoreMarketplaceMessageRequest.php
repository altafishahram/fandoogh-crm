<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMarketplaceMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000', 'required_without:image'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'required_without:body'],
            'client_message_id' => ['nullable', 'uuid'],
        ];
    }
}
