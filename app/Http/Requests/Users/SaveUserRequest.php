<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use App\Support\RolePermissions;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Reject hidden targets before validation. The controller then authorizes
        // the proposed role and assignment values using the same policy.
        $target = $this->route('managedUser');
        if ($target instanceof User) {
            return Gate::allows('update', $target);
        }
        $tenant = Gate::allows('users.manageAll') ? null : app(RolePermissions::class)->singleTenant($this->user());

        return Gate::allows('create', [User::class, $tenant]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'email' => is_string($this->email) ? Str::lower(trim($this->email)) : $this->email,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $target = $this->route('managedUser');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target instanceof User ? $target : null)],
            'password' => [$target instanceof User ? 'nullable' : 'required', 'string', Password::min(8), 'confirmed'],
            'role' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! app(RolePermissions::class)->isKnownRole($value)) {
                    $fail('Role aplikasi tidak dikenal.');
                }
            }],
            'team_ids' => ['present', 'array'],
            'team_ids.*' => ['required', 'integer', 'distinct', Rule::exists('teams', 'id')->whereNull('deleted_at')->where('is_personal', false)],
            'assigned_farm_id' => ['nullable', 'uuid', Rule::exists('farms', 'id')],
            'role_id' => ['prohibited'],
            'current_team_id' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'is_active' => ['prohibited'],
        ];
    }
}
