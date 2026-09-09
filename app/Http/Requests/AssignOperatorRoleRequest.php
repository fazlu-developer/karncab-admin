<?php

namespace App\Http\Requests;

use App\Platform\OperatorRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignOperatorRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.write') === true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(OperatorRole::OPERATOR_ROLES)],
            'nest_user_id' => ['nullable', 'integer', 'min:1'],
            'state_id' => ['nullable', 'integer', 'min:1'],
            'district_id' => ['nullable', 'integer', 'min:1'],
            'fleet_owner_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
