<?php

declare(strict_types=1);

namespace App\Http\Requests\Trainer;

use App\Http\Requests\Availability\AvailabilityGridRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AvailabilityCountRequest extends FormRequest
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
            'day' => ['required', 'integer', 'between:0,6'],
            'start' => ['required', 'regex:'.AvailabilityGridRequest::START_PATTERN],
            'end' => ['required', 'regex:'.AvailabilityGridRequest::END_PATTERN, 'after:start'],
        ];
    }
}
