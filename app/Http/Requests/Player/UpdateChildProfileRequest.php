<?php

declare(strict_types=1);

namespace App\Http\Requests\Player;

use App\Concerns\ChildProfileRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChildProfileRequest extends FormRequest
{
    use ChildProfileRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('childProfile')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->childRules();
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
        $this->merge(['allow_token_spending' => $this->boolean('allow_token_spending')]);
    }
}
