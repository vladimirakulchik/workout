<?php

declare(strict_types=1);

namespace App\Http\Requests\Player;

use App\Concerns\ChildProfileRules;
use App\Concerns\PasswordValidationRules;
use App\Models\PlayerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChildProfileRequest extends FormRequest
{
    use ChildProfileRules, PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', PlayerProfile::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            ...$this->childRules(),
            'trainer_ids' => ['nullable', 'array'],
            'trainer_ids.*' => ['integer', Rule::in($this->user()->connectedTrainerIds())],
            'confirm' => ['boolean'],
            'has_login' => ['boolean'],
        ];

        if ($this->boolean('has_login')) {
            $rules = [...$rules, ...$this->childLoginRules(), 'password' => $this->passwordRules()];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->childMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower((string) $this->input('email')),
            'allow_token_spending' => $this->boolean('allow_token_spending'),
            'has_login' => $this->boolean('has_login'),
            'confirm' => $this->boolean('confirm'),
        ]);
    }
}
