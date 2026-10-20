<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JoinRegistrationRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    private const ADULT_AGE = 18;

    private const MIN_CHILD_AGE = 1;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isParent = $this->boolean('is_parent');

        return [
            'first_name' => $this->nameRules(),
            'last_name' => $this->nameRules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'phone' => $this->phoneRules(),
            'is_parent' => ['boolean'],
            'also_trains' => ['boolean'],
            'player_first_name' => [Rule::requiredIf($isParent), 'nullable', 'string', 'max:255'],
            'player_last_name' => [Rule::requiredIf($isParent), 'nullable', 'string', 'max:255'],
            'birth_date' => $isParent
                ? ['required', 'date', 'before_or_equal:'.now()->subYears(self::MIN_CHILD_AGE)->toDateString(), 'after:'.now()->subYears(self::ADULT_AGE + 1)->toDateString()]
                : ['required', 'date', 'before_or_equal:'.now()->subYears(self::ADULT_AGE)->toDateString()],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birth_date.before_or_equal' => $this->boolean('is_parent')
                ? __('A child must be at least 1 year old.')
                : __('Players under 18 need a parent account. Please register as a parent instead.'),
            'birth_date.after' => __('A child must be 18 or younger.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower((string) $this->input('email')),
            'is_parent' => $this->boolean('is_parent'),
            'also_trains' => $this->boolean('is_parent') && $this->boolean('also_trains'),
        ]);
    }
}
