<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAgentPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $allowed = array_map(
            static fn (PermissionName $permission): string => $permission->value,
            RoleName::agentConfigurablePermissions(),
        );

        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in($allowed)],
        ];
    }
}
