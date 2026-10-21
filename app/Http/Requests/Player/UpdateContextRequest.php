<?php

declare(strict_types=1);

namespace App\Http\Requests\Player;

use App\Models\User;
use App\Support\ContextResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContextRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'player_id' => ['required', 'integer'],
            'trainer_id' => ['required', 'integer'],
        ];
    }

    /**
     * The pair must be one of the user's own options (own profile or own children, active association).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if ($validator->errors()->isNotEmpty() || ! $user instanceof User) {
                    return;
                }

                if (! app(ContextResolver::class)->isAllowed($user, (int) $this->input('player_id'), (int) $this->input('trainer_id'))) {
                    $validator->errors()->add('player_id', 'You cannot switch to this context.');
                }
            },
        ];
    }
}
