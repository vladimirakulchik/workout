<?php

declare(strict_types=1);

namespace App\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;

class AddChildTrainerRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required_without:trainer_id', 'nullable', 'string', 'max:255'],
            'trainer_id' => ['required_without:code', 'nullable', 'integer'],
        ];
    }
}
