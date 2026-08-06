<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Shared;

use Illuminate\Foundation\Http\FormRequest;

final class NoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:1', 'max:5000']];
    }
}
