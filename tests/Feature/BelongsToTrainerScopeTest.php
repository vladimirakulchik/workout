<?php

namespace Tests\Feature;

use App\Models\CoachProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelongsToTrainerScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    private User $otherTrainer;

    private CoachProfile $ownCoach;

    private CoachProfile $otherCoach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = User::factory()->trainer()->create();
        $this->otherTrainer = User::factory()->trainer()->create();
        $this->ownCoach = CoachProfile::factory()->forTrainer($this->trainer)->create();
        $this->otherCoach = CoachProfile::factory()->forTrainer($this->otherTrainer)->create();
    }

    public function test_no_scope_without_authenticated_user(): void
    {
        $this->assertSame(2, CoachProfile::count());
    }

    public function test_trainer_only_sees_own_rows(): void
    {
        $this->actingAs($this->trainer);

        $this->assertSame([$this->ownCoach->id], CoachProfile::pluck('id')->all());
        $this->assertNull(CoachProfile::find($this->otherCoach->id));
    }

    public function test_coach_only_sees_rows_of_their_trainer(): void
    {
        $this->actingAs($this->ownCoach->user);

        $this->assertSame([$this->ownCoach->id], CoachProfile::pluck('id')->all());
    }

    public function test_coach_without_trainer_sees_nothing(): void
    {
        $unassigned = CoachProfile::factory()->create();

        $this->actingAs($unassigned->user);

        $this->assertSame(0, CoachProfile::count());
    }

    public function test_super_admin_and_player_are_not_scoped(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $this->assertSame(2, CoachProfile::count());

        $this->actingAs(User::factory()->player()->create());
        $this->assertSame(2, CoachProfile::count());
    }

    public function test_scope_can_be_removed_explicitly(): void
    {
        $this->actingAs($this->trainer);

        $this->assertSame(2, CoachProfile::withoutGlobalScopes()->count());
    }
}
