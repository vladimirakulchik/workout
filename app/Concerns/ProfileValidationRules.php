<?php

namespace App\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\File;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate the editable profile fields of a user.
     *
     * @return array<string, array<int, ValidationRule|File|array<mixed>|string>>
     */
    protected function profileRules(User $user): array
    {
        return [
            'first_name' => $this->nameRules(),
            'last_name' => $this->nameRules(),
            'phone' => $this->phoneRules(),
            'photo' => $this->photoRules(),
            ...$this->roleProfileRules($user->role),
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function roleProfileRules(UserRole $role): array
    {
        return match ($role) {
            UserRole::Player => [
                'school' => ['nullable', 'string', 'max:255'],
                'jersey_number' => ['nullable', 'string', 'regex:/^\d{1,3}$/'],
                'emergency_contact_name' => ['required', 'string', 'max:255'],
                'emergency_contact_phone' => $this->phoneRules(),
            ],
            UserRole::Coach => [
                'bio' => ['nullable', 'string', 'max:5000'],
                'credentials' => ['nullable', 'string', 'max:5000'],
                'certifications' => ['nullable', 'string', 'max:5000'],
                'is_public' => ['required', 'boolean'],
            ],
            UserRole::Trainer => [
                'business_name' => ['required', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:500'],
                'website' => ['nullable', 'url:http,https', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
            ],
            UserRole::SuperAdmin => [],
        };
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Simple E.164-style phone number: optional +, digits and common separators.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function phoneRules(): array
    {
        return ['nullable', 'string', 'regex:/^\+?[0-9][0-9\s\-().]{5,18}[0-9]$/'];
    }

    /**
     * @return array<int, ValidationRule|File|string>
     */
    protected function photoRules(): array
    {
        return ['nullable', File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(2048)];
    }
}
