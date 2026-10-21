<?php

declare(strict_types=1);

namespace App\Http\Requests\Trainer;

use App\Enums\UserRole;
use App\Models\ShareLink;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InviteCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ShareLink::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_email' => [
                'required', 'string', 'email', 'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (User::query()->where('email', $value)->where('role', '!=', UserRole::Coach)->exists()) {
                        $fail(__('An account with this email already exists and is not a coach account.'));
                    }
                },
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['target_email' => mb_strtolower(trim((string) $this->input('target_email')))]);
    }
}
