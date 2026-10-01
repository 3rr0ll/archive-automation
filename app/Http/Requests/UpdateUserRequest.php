<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'role' => ['sometimes', 'required', 'in:user,admin'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var User|null $target */
            $target = $this->route('user');
            $actor = $this->user();

            if (! $target instanceof User || ! $actor instanceof User) {
                return;
            }

            if ($target->isAdmin() && ! $actor->is($target)) {
                $validator->errors()->add('user', 'You cannot modify another admin account.');

                return;
            }

            if ($actor->is($target) && $this->filled('role') && $this->input('role') !== 'admin') {
                $validator->errors()->add('role', 'You cannot remove your own admin role.');

                return;
            }

            if ($this->input('role') === 'admin' && User::anotherAdminExists($target->id)) {
                $validator->errors()->add('role', 'An admin account already exists. Only one admin is allowed.');
            }
        });
    }
}
