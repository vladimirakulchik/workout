<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\CoachProfile;
use App\Models\PlayerProfile;
use App\Models\TrainerPlayerAssociation;
use App\Models\User;
use App\Services\AvailabilitySummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function range(int $day, string $start, string $end): array
    {
        return ['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end];
    }

    private function playerWithProfile(): array
    {
        $user = User::factory()->player()->create();
        $profile = PlayerProfile::factory()->create(['user_id' => $user->id]);

        return [$user, $profile];
    }

    private function connect(User $trainer, PlayerProfile $profile): void
    {
        TrainerPlayerAssociation::factory()->forTrainer($trainer)->forProfile($profile)->create();
    }

    public function test_player_saves_and_replaces_availability(): void
    {
        [$user, $profile] = $this->playerWithProfile();
        Availability::factory()->forOwner($profile)->between(2, '09:00', '10:00')->create();

        $this->actingAs($user)
            ->put(route('player.availability.update'), [
                'profile_id' => $profile->id,
                'ranges' => [
                    $this->range(3, '18:00', '21:00'),
                    $this->range(1, '17:00', '20:00'),
                    $this->range(1, '07:00', '08:30'),
                ],
            ])
            ->assertRedirect(route('player.availability.edit', ['profile' => $profile->id]))
            ->assertInertiaFlash('toast.message', 'Availability saved. Trainers can see these preferences when planning sessions.');

        $this->assertSame(
            ['1 07:00:00-08:30:00', '1 17:00:00-20:00:00', '3 18:00:00-21:00:00'],
            $profile->availabilities()->get()->map(fn (Availability $a): string => "$a->day_of_week $a->start_time-$a->end_time")->all(),
        );
    }

    public function test_saving_an_empty_grid_clears_availability(): void
    {
        [$user, $profile] = $this->playerWithProfile();
        Availability::factory()->forOwner($profile)->count(2)->create();

        $this->actingAs($user)
            ->put(route('player.availability.update'), ['profile_id' => $profile->id, 'ranges' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $profile->availabilities()->count());
    }

    public function test_edit_page_shows_saved_ranges(): void
    {
        [$user, $profile] = $this->playerWithProfile();
        Availability::factory()->forOwner($profile)->between(1, '17:00', '20:00')->create();

        $this->actingAs($user)
            ->get(route('player.availability.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('player/Availability')
                ->where('profile.id', $profile->id)
                ->where('ranges.0', $this->range(1, '17:00', '20:00')));
    }

    /**
     * @param  list<array<string, mixed>>  $ranges
     */
    #[DataProvider('invalidGrids')]
    public function test_invalid_grids_are_rejected(array $ranges, string $errorKey): void
    {
        [$user, $profile] = $this->playerWithProfile();

        $this->actingAs($user)
            ->put(route('player.availability.update'), ['profile_id' => $profile->id, 'ranges' => $ranges])
            ->assertSessionHasErrors($errorKey);

        $this->assertSame(0, $profile->availabilities()->count());
    }

    /**
     * @return array<string, array{0: list<array<string, mixed>>, 1: string}>
     */
    public static function invalidGrids(): array
    {
        return [
            'end before start' => [[['day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '17:00']], 'ranges.0.end_time'],
            'end equals start' => [[['day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '18:00']], 'ranges.0.end_time'],
            'overlap on a day' => [[
                ['day_of_week' => 1, 'start_time' => '17:00', 'end_time' => '19:00'],
                ['day_of_week' => 1, 'start_time' => '18:30', 'end_time' => '20:00'],
            ], 'ranges.1.start_time'],
            'not on the 30 minute step' => [[['day_of_week' => 1, 'start_time' => '17:15', 'end_time' => '18:00']], 'ranges.0.start_time'],
            'bad day' => [[['day_of_week' => 7, 'start_time' => '17:00', 'end_time' => '18:00']], 'ranges.0.day_of_week'],
        ];
    }

    public function test_touching_ranges_and_same_times_on_different_days_are_allowed(): void
    {
        [$user, $profile] = $this->playerWithProfile();

        $this->actingAs($user)
            ->put(route('player.availability.update'), ['profile_id' => $profile->id, 'ranges' => [
                $this->range(1, '17:00', '18:00'),
                $this->range(1, '18:00', '24:00'),
                $this->range(2, '17:00', '19:00'),
            ]])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $profile->availabilities()->count());
    }

    public function test_parent_sets_availability_per_child(): void
    {
        $parent = User::factory()->player()->create();
        $first = PlayerProfile::factory()->child($parent)->create();
        $second = PlayerProfile::factory()->child($parent)->create();

        $this->actingAs($parent)
            ->put(route('player.availability.update'), ['profile_id' => $first->id, 'ranges' => [$this->range(1, '17:00', '18:00')]])
            ->assertSessionHasNoErrors();
        $this->actingAs($parent)
            ->put(route('player.availability.update'), ['profile_id' => $second->id, 'ranges' => [$this->range(4, '10:00', '11:00')]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $first->availabilities()->where('day_of_week', 1)->count());
        $this->assertSame(0, $first->availabilities()->where('day_of_week', 4)->count());
        $this->assertSame(1, $second->availabilities()->where('day_of_week', 4)->count());

        $this->actingAs($parent)
            ->get(route('player.availability.edit', ['profile' => $second->id]))
            ->assertInertia(fn ($page) => $page
                ->where('profile.id', $second->id)
                ->has('profiles', 2)
                ->where('ranges.0.day_of_week', 4));
    }

    public function test_player_cannot_edit_someone_elses_profile(): void
    {
        [$user] = $this->playerWithProfile();
        $stranger = PlayerProfile::factory()->create();

        $this->actingAs($user)
            ->put(route('player.availability.update'), ['profile_id' => $stranger->id, 'ranges' => [$this->range(1, '17:00', '18:00')]])
            ->assertForbidden();

        $this->assertSame(0, $stranger->availabilities()->count());

        $this->actingAs($user)
            ->get(route('player.availability.edit', ['profile' => $stranger->id]))
            ->assertInertia(fn ($page) => $page->where('profile.id', fn ($id) => $id !== $stranger->id));
    }

    public function test_child_login_can_set_own_times(): void
    {
        $parent = User::factory()->player()->create();
        $childLogin = User::factory()->player()->create();
        $child = PlayerProfile::factory()->child($parent)->create(['user_id' => $childLogin->id]);

        $this->actingAs($childLogin)
            ->put(route('player.availability.update'), ['profile_id' => $child->id, 'ranges' => [$this->range(1, '17:00', '18:00')]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $child->availabilities()->count());
    }

    public function test_only_players_use_player_availability(): void
    {
        $this->actingAs(User::factory()->trainer()->create())
            ->get(route('player.availability.edit'))
            ->assertForbidden();
    }

    public function test_coach_sets_own_availability(): void
    {
        $trainer = User::factory()->trainer()->create();
        $coach = User::factory()->coach()->create();
        $profile = CoachProfile::factory()->forTrainer($trainer)->create(['user_id' => $coach->id]);

        $this->actingAs($coach)
            ->put(route('coach.availability.update'), ['ranges' => [$this->range(5, '17:00', '19:00')]])
            ->assertRedirect(route('coach.availability.edit'));

        $this->assertSame(1, $profile->availabilities()->count());

        $this->actingAs($coach)
            ->get(route('coach.availability.edit'))
            ->assertInertia(fn ($page) => $page->component('coach/MyTimes')->where('ranges.0.day_of_week', 5)->where('isPublic', false));
    }

    public function test_coach_keeps_multiple_slots_per_day_and_replaces_them(): void
    {
        $trainer = User::factory()->trainer()->create();
        $coach = User::factory()->coach()->create();
        $profile = CoachProfile::factory()->forTrainer($trainer)->public()->create(['user_id' => $coach->id]);

        $this->actingAs($coach)
            ->put(route('coach.availability.update'), ['ranges' => [
                $this->range(2, '08:00', '10:00'),
                $this->range(2, '17:00', '19:00'),
            ]])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $profile->availabilities()->where('day_of_week', 2)->count());

        $this->actingAs($coach)
            ->get(route('coach.availability.edit'))
            ->assertInertia(fn ($page) => $page->where('isPublic', true)->has('ranges', 2));

        $this->actingAs($coach)->put(route('coach.availability.update'), ['ranges' => []]);

        $this->assertSame(0, $profile->availabilities()->count());
    }

    public function test_coach_cannot_change_another_coachs_availability(): void
    {
        $trainer = User::factory()->trainer()->create();
        $coach = User::factory()->coach()->create();
        $other = User::factory()->coach()->create();
        CoachProfile::factory()->forTrainer($trainer)->create(['user_id' => $coach->id]);
        $otherProfile = CoachProfile::factory()->forTrainer($trainer)->create(['user_id' => $other->id]);
        $otherProfile->availabilities()->create($this->range(1, '09:00', '10:00'));

        $this->actingAs($coach)
            ->put(route('coach.availability.update'), ['ranges' => [$this->range(5, '17:00', '19:00')]]);

        $this->assertSame(1, $otherProfile->availabilities()->count());
        $this->assertSame(1, $otherProfile->availabilities()->where('day_of_week', 1)->count());
    }

    public function test_trainer_and_player_cannot_use_coach_times_pages(): void
    {
        $this->actingAs(User::factory()->trainer()->create())->get(route('coach.availability.edit'))->assertForbidden();
        $this->actingAs(User::factory()->player()->create())->put(route('coach.availability.update'), ['ranges' => []])->assertForbidden();
    }

    public function test_trainer_views_own_coach_times_read_only(): void
    {
        $trainer = User::factory()->trainer()->create();
        $profile = CoachProfile::factory()->forTrainer($trainer)->public()->create();
        $profile->availabilities()->create($this->range(3, '18:00', '20:00'));

        $this->actingAs($trainer)
            ->get(route('trainer.coaches.show', $profile))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('trainer/Coaches/Show')
                ->where('coach.is_public', true)
                ->where('ranges.0.day_of_week', 3)
                ->where('ranges.0.start_time', '18:00'));

        $this->actingAs($trainer)
            ->put(route('coach.availability.update'), ['ranges' => []])
            ->assertForbidden();
    }

    public function test_trainer_cannot_view_another_trainers_coach(): void
    {
        $profile = CoachProfile::factory()->forTrainer(User::factory()->trainer()->create())->create();

        $this->actingAs(User::factory()->trainer()->create())
            ->get(route('trainer.coaches.show', $profile))
            ->assertNotFound();

        $this->actingAs(User::factory()->coach()->create())
            ->get(route('trainer.coaches.show', $profile))
            ->assertNotFound();
    }

    public function test_summary_formats_best_times(): void
    {
        $profile = PlayerProfile::factory()->create();
        Availability::factory()->forOwner($profile)->between(3, '18:00', '21:00')->create();
        Availability::factory()->forOwner($profile)->between(1, '17:00', '20:00')->create();
        Availability::factory()->forOwner($profile)->between(0, '11:30', '13:00')->create();

        $this->assertSame(
            'Mon 5-8pm, Wed 6-9pm, Sun 11:30am-1pm',
            (new AvailabilitySummary)->summarize($profile->availabilities()->get()),
        );
        $this->assertSame('', (new AvailabilitySummary)->summarize([]));
    }

    public function test_trainer_lists_players_with_best_times_search_and_pagination(): void
    {
        $trainer = User::factory()->trainer()->create();
        $anna = PlayerProfile::factory()->create(['first_name' => 'Anna', 'last_name' => 'Zed']);
        $bob = PlayerProfile::factory()->create(['first_name' => 'Bob', 'last_name' => 'Zed']);
        $this->connect($trainer, $anna);
        $this->connect($trainer, $bob);
        Availability::factory()->forOwner($anna)->between(1, '17:00', '20:00')->create();
        Availability::factory()->forOwner($anna)->between(3, '18:00', '21:00')->create();

        $this->actingAs($trainer)
            ->get(route('trainer.players.index'))
            ->assertInertia(fn ($page) => $page
                ->component('trainer/Players/Index')
                ->has('players.data', 2)
                ->where('players.data.0.name', 'Anna Zed')
                ->where('players.data.0.best_times', 'Mon 5-8pm, Wed 6-9pm')
                ->where('players.data.1.best_times', ''));

        $this->actingAs($trainer)
            ->get(route('trainer.players.index', ['search' => 'bob']))
            ->assertInertia(fn ($page) => $page->has('players.data', 1)->where('players.data.0.id', $bob->id));
    }

    public function test_trainer_filters_players_by_window(): void
    {
        $trainer = User::factory()->trainer()->create();
        $covers = PlayerProfile::factory()->create();
        $partial = PlayerProfile::factory()->create();
        $otherDay = PlayerProfile::factory()->create();
        $none = PlayerProfile::factory()->create();

        foreach ([$covers, $partial, $otherDay, $none] as $profile) {
            $this->connect($trainer, $profile);
        }

        Availability::factory()->forOwner($covers)->between(1, '16:00', '21:00')->create();
        Availability::factory()->forOwner($partial)->between(1, '17:00', '19:00')->create();
        Availability::factory()->forOwner($otherDay)->between(2, '16:00', '21:00')->create();

        $this->actingAs($trainer)
            ->get(route('trainer.players.index', ['day' => 1, 'from' => '17:00', 'to' => '20:00']))
            ->assertInertia(fn ($page) => $page->has('players.data', 1)->where('players.data.0.id', $covers->id));

        $this->actingAs($trainer)
            ->getJson(route('trainer.players.availability-count', ['day' => 1, 'start' => '17:00', 'end' => '19:00']))
            ->assertOk()
            ->assertExactJson(['available' => 2, 'total' => 4]);
    }

    public function test_window_filter_requires_all_parts_and_valid_order(): void
    {
        $trainer = User::factory()->trainer()->create();

        $this->actingAs($trainer)
            ->get(route('trainer.players.index', ['day' => 1, 'from' => '17:00']))
            ->assertSessionHasErrors('to');
        $this->actingAs($trainer)
            ->get(route('trainer.players.index', ['day' => 1, 'from' => '20:00', 'to' => '17:00']))
            ->assertSessionHasErrors('to');
        $this->actingAs($trainer)
            ->getJson(route('trainer.players.availability-count', ['day' => 1, 'start' => '20:00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end']);
    }

    public function test_trainer_only_sees_own_players_and_counts(): void
    {
        $trainer = User::factory()->trainer()->create();
        $other = User::factory()->trainer()->create();
        $mine = PlayerProfile::factory()->create();
        $theirs = PlayerProfile::factory()->create();
        $this->connect($trainer, $mine);
        $this->connect($other, $theirs);
        Availability::factory()->forOwner($mine)->between(1, '17:00', '20:00')->create();
        Availability::factory()->forOwner($theirs)->between(1, '17:00', '20:00')->create();

        $this->actingAs($trainer)
            ->get(route('trainer.players.index'))
            ->assertInertia(fn ($page) => $page->has('players.data', 1)->where('players.data.0.id', $mine->id));

        $this->actingAs($trainer)
            ->getJson(route('trainer.players.availability-count', ['day' => 1, 'start' => '17:00', 'end' => '20:00']))
            ->assertExactJson(['available' => 1, 'total' => 1]);
    }

    public function test_removed_associations_are_not_listed(): void
    {
        $trainer = User::factory()->trainer()->create();
        $profile = PlayerProfile::factory()->create();
        $this->connect($trainer, $profile);
        TrainerPlayerAssociation::query()->where('player_profile_id', $profile->id)->delete();

        $this->actingAs($trainer)
            ->get(route('trainer.players.index'))
            ->assertInertia(fn ($page) => $page->has('players.data', 0));
    }

    public function test_trainer_players_are_not_available_to_other_roles(): void
    {
        [$player] = $this->playerWithProfile();

        $this->actingAs($player)->get(route('trainer.players.index'))->assertForbidden();
        $this->actingAs($player)->getJson(route('trainer.players.availability-count', ['day' => 1, 'start' => '17:00', 'end' => '18:00']))->assertForbidden();
    }

    public function test_listing_five_thousand_players_stays_cheap_and_indexed(): void
    {
        $trainer = User::factory()->trainer()->create();
        $now = now()->toDateTimeString();

        $profiles = [];
        for ($i = 0; $i < 5000; $i++) {
            $profiles[] = [
                'first_name' => 'P'.$i, 'last_name' => 'Load', 'gender' => 'other', 'is_child' => false,
                'emergency_contact_name' => 'X', 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($profiles, 500) as $chunk) {
            DB::table('player_profiles')->insert($chunk);
        }

        $ids = DB::table('player_profiles')->pluck('id');
        $associations = $availabilities = [];
        foreach ($ids as $id) {
            $associations[] = ['trainer_id' => $trainer->id, 'player_profile_id' => $id, 'connected_at' => $now, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now];
            $availabilities[] = ['availabilityable_type' => (new PlayerProfile)->getMorphClass(), 'availabilityable_id' => $id, 'day_of_week' => $id % 7, 'start_time' => '17:00:00', 'end_time' => '20:00:00', 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($associations, 500) as $chunk) {
            DB::table('trainer_player_associations')->insert($chunk);
        }
        foreach (array_chunk($availabilities, 500) as $chunk) {
            DB::table('availabilities')->insert($chunk);
        }

        $this->assertContains(
            ['availabilityable_type', 'availabilityable_id', 'day_of_week'],
            array_column(Schema::getIndexes('availabilities'), 'columns'),
        );

        DB::enableQueryLog();

        $this->actingAs($trainer)
            ->get(route('trainer.players.index', ['day' => 1, 'from' => '17:00', 'to' => '20:00']))
            ->assertInertia(fn ($page) => $page->where('players.total', $ids->filter(fn ($id): bool => $id % 7 === 1)->count())->has('players.data', 20));

        $selects = array_filter(DB::getQueryLog(), fn (array $query): bool => str_starts_with($query['query'], 'select') && str_contains($query['query'], 'availabilities'));
        $this->assertLessThanOrEqual(3, count($selects));

        $plan = collect(DB::select('explain query plan select 1 from availabilities where availabilityable_type = ? and availabilityable_id = ? and day_of_week = ?', ['x', 1, 1]))
            ->pluck('detail')->implode(' ');
        $this->assertStringContainsString('availabilities_owner_day_index', $plan);
    }
}
