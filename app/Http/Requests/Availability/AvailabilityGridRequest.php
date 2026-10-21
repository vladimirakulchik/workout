<?php

declare(strict_types=1);

namespace App\Http\Requests\Availability;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Weekly availability grid: ranges on a 30-minute step, end after start, no overlap within a day.
 * Authorization of the owning profile is done by the controller.
 */
class AvailabilityGridRequest extends FormRequest
{
    public const START_PATTERN = '/^([01]\d|2[0-3]):(00|30)$/';

    public const END_PATTERN = '/^(([01]\d|2[0-3]):(00|30)|24:00)$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'profile_id' => ['nullable', 'integer'],
            'ranges' => ['present', 'array', 'max:70'],
            'ranges.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'ranges.*.start_time' => ['required', 'string', 'regex:'.self::START_PATTERN],
            'ranges.*.end_time' => ['required', 'string', 'regex:'.self::END_PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ranges.*.start_time.regex' => 'Times must be on the hour or half hour.',
            'ranges.*.end_time.regex' => 'Times must be on the hour or half hour.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var list<array{day_of_week: int|string, start_time: string, end_time: string}> $ranges */
            $ranges = $this->input('ranges', []);
            $byDay = [];

            foreach ($ranges as $index => $range) {
                if ($range['end_time'] <= $range['start_time']) {
                    $validator->errors()->add("ranges.$index.end_time", 'The end time must be after the start time.');

                    continue;
                }

                $byDay[(int) $range['day_of_week']][$index] = $range;
            }

            foreach ($byDay as $dayRanges) {
                uasort($dayRanges, fn (array $a, array $b): int => $a['start_time'] <=> $b['start_time']);

                $previous = null;

                foreach ($dayRanges as $index => $range) {
                    if ($previous !== null && $range['start_time'] < $previous['end_time']) {
                        $validator->errors()->add("ranges.$index.start_time", 'Time ranges on the same day must not overlap.');
                    }

                    $previous = $range;
                }
            }
        }];
    }

    /**
     * @return list<array{day_of_week: int, start_time: string, end_time: string}>
     */
    public function ranges(): array
    {
        $ranges = [];

        foreach ($this->safe()->array('ranges') as $range) {
            $ranges[] = [
                'day_of_week' => (int) $range['day_of_week'],
                'start_time' => (string) $range['start_time'],
                'end_time' => (string) $range['end_time'],
            ];
        }

        usort($ranges, fn (array $a, array $b): int => [$a['day_of_week'], $a['start_time']] <=> [$b['day_of_week'], $b['start_time']]);

        return $ranges;
    }
}
