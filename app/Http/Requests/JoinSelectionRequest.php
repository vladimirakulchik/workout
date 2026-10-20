<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinSelectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'profile_ids' => ['required', 'array', 'min:1'],
            'profile_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'profile_ids.required' => __('Choose at least one person.'),
            'profile_ids.min' => __('Choose at least one person.'),
        ];
    }
}
