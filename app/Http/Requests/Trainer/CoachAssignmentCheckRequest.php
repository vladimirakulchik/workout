<?php

declare(strict_types=1);

namespace App\Http\Requests\Trainer;

use App\Models\CoachProfile;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CoachAssignmentCheckRequest extends FormRequest
{
    private ?CoachProfile $coachProfile = null;

    public function authorize(): bool
    {
        $coachProfile = $this->coachProfile();

        return $coachProfile !== null && $this->user()->can('assign', $coachProfile);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'coach_profile_id' => ['required', 'integer'],
            'start' => ['required', 'date_format:Y-m-d\TH:i'],
            'end' => ['required', 'date_format:Y-m-d\TH:i', 'after:start'],
        ];
    }

    /**
     * Looked up without the tenant scope so another trainer's coach is forbidden rather than missing.
     */
    public function coachProfile(): ?CoachProfile
    {
        return $this->coachProfile ??= CoachProfile::query()
            ->withoutGlobalScope('trainer')
            ->with('user')
            ->find((int) $this->input('coach_profile_id'));
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $this->input('start'));
    }

    public function end(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $this->input('end'));
    }
}
