<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

trait ChildProfileRules
{
    private const MIN_CHILD_AGE = 1;

    private const MAX_CHILD_AGE = 18;

    /**
     * Rules for the child's own details (age 1-18, gender required, school and photo optional).
     *
     * @return array<string, array<int, mixed>>
     */
    protected function childRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birth_date' => [
                'required',
                'date',
                'before_or_equal:'.now()->subYears(self::MIN_CHILD_AGE)->toDateString(),
                'after:'.now()->subYears(self::MAX_CHILD_AGE + 1)->toDateString(),
            ],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'school' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(2048)],
            'allow_token_spending' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function childMessages(): array
    {
        return [
            'birth_date.before_or_equal' => __('A child must be at least 1 year old.'),
            'birth_date.after' => __('A child must be 18 or younger.'),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function childLoginRules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
        ];
    }
}
