<?php

namespace Tests\Feature;

use App\Models\CoachProfile;
use App\Models\PlayerProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProfileRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_one_trainer_and_coach_profile(): void
    {
        $trainer = TrainerProfile::factory()->create();
        $coach = CoachProfile::factory()->create();

        $this->assertTrue($trainer->user->trainerProfile->is($trainer));
        $this->assertTrue($coach->user->coachProfile->is($coach));
    }

    public function test_trainer_profile_user_id_is_unique(): void
    {
        $profile = TrainerProfile::factory()->create();

        $this->expectException(QueryException::class);

        TrainerProfile::factory()->create(['user_id' => $profile->user_id]);
    }

    public function test_trainer_has_many_coaches_and_coach_has_trainer(): void
    {
        $trainer = User::factory()->trainer()->create();
        $coachProfile = CoachProfile::factory()->forTrainer($trainer)->create();
        CoachProfile::factory()->create();

        $this->assertSame([$coachProfile->user_id], $trainer->coaches()->pluck('users.id')->all());
        $this->assertTrue($coachProfile->trainer->is($trainer));
        $this->assertNull(CoachProfile::factory()->create()->trainer);
    }

    public function test_self_profile_belongs_to_owner_without_parent(): void
    {
        $profile = PlayerProfile::factory()->self()->create();

        $this->assertTrue($profile->user->selfPlayerProfile->is($profile));
        $this->assertCount(1, $profile->user->playerProfiles);
        $this->assertNull($profile->parent);
        $this->assertFalse($profile->is_child);
        $this->assertFalse($profile->user->isChildAccount());
    }

    public function test_child_profile_belongs_to_parent_and_may_lack_login(): void
    {
        $parent = User::factory()->create();
        $child = PlayerProfile::factory()->child($parent)->create();

        $this->assertNull($child->user_id);
        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->childProfiles->contains($child));
        $this->assertCount(0, $parent->playerProfiles);
    }

    public function test_child_with_own_login_is_a_child_account(): void
    {
        $parent = User::factory()->create();
        $login = User::factory()->player()->create();
        PlayerProfile::factory()->child($parent)->create(['user_id' => $login->id]);

        $this->assertTrue($login->isChildAccount());
        $this->assertCount(1, $parent->childProfiles);
    }

    public function test_age_accessor_returns_whole_years(): void
    {
        Carbon::setTestNow('2026-06-15');

        $profile = PlayerProfile::factory()->make(['birth_date' => '2016-06-16']);
        $this->assertSame(9, $profile->age);

        $profile->birth_date = Carbon::parse('2016-06-15');
        $this->assertSame(10, $profile->age);

        Carbon::setTestNow();
    }

    public function test_deleting_user_cascades_to_profiles(): void
    {
        $profile = TrainerProfile::factory()->create();

        $profile->user->delete();

        $this->assertModelMissing($profile);
    }
}
