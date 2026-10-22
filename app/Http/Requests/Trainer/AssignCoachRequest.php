<?php

declare(strict_types=1);

namespace App\Http\Requests\Trainer;

use App\Services\CoachAvailabilityChecker;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class AssignCoachRequest extends CoachAssignmentCheckRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'event_id' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * `reason` is required when the window conflicts with the coach's availability.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $conflict = ! app(CoachAvailabilityChecker::class)->check($this->coachProfile()->user, $this->start(), $this->end());

            if ($conflict && trim((string) $this->input('reason')) === '') {
                $validator->errors()->add('reason', __('A reason is required to assign an unavailable coach.'));
            }
        }];
    }
}
