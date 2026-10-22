<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\CoachAvailabilityOverride;
use App\Models\CoachProfile;
use App\Models\User;
use App\Services\CoachAvailabilityChecker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /** 2026-11-02 is a Monday. */
    private const MONDAY = '2026-11-02';

    private function trainerWithCoach(): array
    {
        $trainer = User::factory()->trainer()->create();
        $profile = CoachProfile::factory()->forTrainer($trainer)->create();

        Availability::factory()->forOwner($profile)->between(1, '17:00', '20:00')->create();

        return [$trainer, $profile];
    }

    /**
     * @return array<string, mixed>
     */
    private function window(CoachProfile $profile, string $start = '17:30', string $end = '19:00'): array
    {
        return [
            'coach_profile_id' => $profile->id,
            'start' => self::MONDAY.'T'.$start,
            'end' => self::MONDAY.'T'.$end,
        ];
    }

    public function test_check_reports_available_when_window_is_inside_a_range(): void
    {
        [$trainer, $profile] = $this->trainerWithCoach();

        $this->actingAs($trainer)
            ->postJson(route('trainer.coach-assignments.check'), $this->window($profile))
            ->assertOk()
            ->assertExactJson(['available' => true, 'coach_name' => $profile->user->name]);
    }

    public function test_check_reports_conflict_outside_ranges(): void
    {
        [$trainer, $profile] = $this->trainerWithCoach();

        $this->actingAs($trainer)
            ->postJson(route('trainer.coach-assignments.check'), $this->window($profile, '19:00', '21:00'))
            ->assertOk()
            ->assertJsonPath('available', false);
    }

    public function test_checker_handles_edges_other_days_and_midnight(): void
    {
        [, $profile] = $this->trainerWithCoach();
        Availability::factory()->forOwner($profile)->between(1, '22:00', '24:00')->create();
        Availability::factory()->forOwner($profile)->between(2, '00:00', '02:00')->create();
        $checker = new CoachAvailabilityChecker;
        $coach = $profile->user;
        $at = fn (string $date, string $time): CarbonImmutable => CarbonImmutable::parse("$date $time");

        $this->assertTrue($checker->check($coach, $at(self::MONDAY, '17:00'), $at(self::MONDAY, '20:00')));
        $this->assertFalse($checker->check($coach, $at(self::MONDAY, '16:30'), $at(self::MONDAY, '18:00')));
        $this->assertFalse($checker->check($coach, $at(self::MONDAY, '19:00'), $at(self::MONDAY, '20:30')));
        $this->assertFalse($checker->check($coach, $at('2026-11-03', '17:00'), $at('2026-11-03', '18:00')));
        $this->assertTrue($checker->check($coach, $at(self::MONDAY, '23:00'), $at('2026-11-03', '01:00')));
        $this->assertFalse($checker->check($coach, $at(self::MONDAY, '23:00'), $at('2026-11-03', '03:00')));
        $this->assertFalse($checker->check($coach, $at(self::MONDAY, '18:00'), $at(self::MONDAY, '18:00')));
    }

    public function test_coach_without_availability_always_conflicts(): void
    {
        $trainer = User::factory()->trainer()->create();
        $profile = CoachProfile::factory()->forTrainer($trainer)->create();

        $this->actingAs($trainer)
            ->postJson(route('trainer.coach-assignments.check'), $this->window($profile))
            ->assertJsonPath('available', false);
    }

    public function test_assignment_without_conflict_passes_and_logs_nothing(): void
    {
        [$trainer, $profile] = $this->trainerWithCoach();

        $this->actingAs($trainer)
            ->post(route('trainer.coach-assignments.store'), $this->window($profile) + ['event_id' => 5])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Coach assigned.');

        $this->assertSame(0, CoachAvailabilityOverride::query()->count());
    }

    public function test_conflict_without_reason_is_blocked(): void
    {
        [$trainer, $profile] = $this->trainerWithCoach();

        $this->actingAs($trainer)
            ->post(route('trainer.coach-assignments.store'), $this->window($profile, '19:00', '21:00') + ['event_id' => 5, 'reason' => '  '])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, CoachAvailabilityOverride::query()->count());
    }

    public function test_conflict_with_reason_logs_override(): void
    {
        [$trainer, $profile] = $this->trainerWithCoach();

        $this->actingAs($trainer)
            ->post(route('trainer.coach-assignments.store'), $this->window($profile, '19:00', '21:00') + ['event_id' => 5, 'reason' => 'Short staffed'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coach_availability_overrides', [
            'event_id' => 5,
            'coach_id' => $profile->user_id,
            'overridden_by' => $trainer->id,
            'reason' => 'Short staffed',
        ]);
    }

    public function test_invalid_input_is_rejected(): void
    {
        [$trainer, $profile] = $this->trainerWithCoach();

        $this->actingAs($trainer)
            ->post(route('trainer.coach-assignments.store'), $this->window($profile, '19:00', '18:00') + ['event_id' => 5])
            ->assertSessionHasErrors('end');

        $this->actingAs($trainer)
            ->post(route('trainer.coach-assignments.store'), $this->window($profile))
            ->assertSessionHasErrors('event_id');
    }

    public function test_coach_must_belong_to_the_trainer(): void
    {
        [$trainer] = $this->trainerWithCoach();
        $other = User::factory()->trainer()->create();
        $foreign = CoachProfile::factory()->forTrainer($other)->create();
        $removed = CoachProfile::factory()->create();

        foreach ([$foreign, $removed] as $profile) {
            $this->actingAs($trainer)
                ->postJson(route('trainer.coach-assignments.check'), $this->window($profile))
                ->assertForbidden();
            $this->actingAs($trainer)
                ->post(route('trainer.coach-assignments.store'), $this->window($profile) + ['event_id' => 5, 'reason' => 'x'])
                ->assertForbidden();
        }

        $this->assertSame(0, CoachAvailabilityOverride::query()->count());
    }

    public function test_unknown_coach_and_other_roles_are_forbidden(): void
    {
        [$trainer, $profile] = $this->trainerWithCoach();

        $this->actingAs($trainer)
            ->postJson(route('trainer.coach-assignments.check'), ['coach_profile_id' => 9999] + $this->window($profile))
            ->assertForbidden();

        $this->actingAs($profile->user)
            ->postJson(route('trainer.coach-assignments.check'), $this->window($profile))
            ->assertForbidden();
    }

    public function test_show_page_lists_overrides_for_the_coach(): void
    {
        [$trainer, $profile] = $this->trainerWithCoach();
        CoachAvailabilityOverride::factory()->create([
            'event_id' => 42,
            'coach_id' => $profile->user_id,
            'overridden_by' => $trainer->id,
            'reason' => 'Cover for illness',
        ]);
        CoachAvailabilityOverride::factory()->create();

        $this->actingAs($trainer)
            ->get(route('trainer.coaches.show', $profile))
            ->assertInertia(fn ($page) => $page
                ->component('trainer/Coaches/Show')
                ->has('overrides', 1)
                ->where('overrides.0.event_id', 42)
                ->where('overrides.0.reason', 'Cover for illness')
                ->where('overrides.0.overridden_by', $trainer->name));
    }
}
