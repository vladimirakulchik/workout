<?php

declare(strict_types=1);

namespace App\Http\Requests\Trainer;

use App\Http\Requests\Availability\AvailabilityGridRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexPlayersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isTrainer();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'day' => ['nullable', 'integer', 'between:0,6', 'required_with:from,to'],
            'from' => ['nullable', 'regex:'.AvailabilityGridRequest::START_PATTERN, 'required_with:day,to'],
            'to' => ['nullable', 'regex:'.AvailabilityGridRequest::END_PATTERN, 'required_with:day,from', 'after:from'],
        ];
    }

    /**
     * Whether a complete availability window was given.
     */
    public function hasWindow(): bool
    {
        return $this->filled(['day', 'from', 'to']);
    }
}
