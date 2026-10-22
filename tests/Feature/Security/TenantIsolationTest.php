<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\CoachProfile;
use App\Models\PlayerProfile;
use App\Models\ShareLink;
use App\Models\TrainerPlayerAssociation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $trainerA;

    private User $trainerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainerA = User::factory()->trainer()->create();
        $this->trainerB = User::factory()->trainer()->create();
    }

    public function test_trainer_cannot_see_another_trainers_players(): void
    {
        $own = PlayerProfile::factory()->create(['last_name' => 'Ownplayerzz']);
        $foreign = PlayerProfile::factory()->create(['last_name' => 'Foreignplayerzz']);
        TrainerPlayerAssociation::factory()->forTrainer($this->trainerA)->forProfile($own)->create();
        TrainerPlayerAssociation::factory()->forTrainer($this->trainerB)->forProfile($foreign)->create();

        $this->actingAs($this->trainerA)
            ->get(route('trainer.players.index'))
            ->assertOk()
            ->assertSee('Ownplayerzz')
            ->assertDontSee('Foreignplayerzz');
    }

    public function test_trainer_cannot_open_or_remove_another_trainers_coach(): void
    {
        $foreignCoach = CoachProfile::factory()->forTrainer($this->trainerB)->create();

        $this->actingAs($this->trainerA)
            ->get(route('trainer.coaches.show', $foreignCoach))
            ->assertNotFound();

        $this->actingAs($this->trainerA)
            ->delete(route('trainer.coaches.destroy', $foreignCoach))
            ->assertNotFound();

        $this->assertSame($this->trainerB->id, $foreignCoach->fresh()->trainer_id);
    }

    public function test_trainer_coach_list_excludes_other_trainers_coaches(): void
    {
        CoachProfile::factory()->forTrainer($this->trainerA)->create();
        $foreignCoach = CoachProfile::factory()->forTrainer($this->trainerB)->create();
        $foreignCoach->user->forceFill(['last_name' => 'Foreigncoachzz'])->save();

        $this->actingAs($this->trainerA)
            ->get(route('trainer.coaches.index'))
            ->assertOk()
            ->assertDontSee('Foreigncoachzz');
    }

    public function test_trainer_cannot_deactivate_another_trainers_share_link(): void
    {
        $foreignLink = ShareLink::factory()->forTrainer($this->trainerB)->create();

        $this->actingAs($this->trainerA)
            ->delete(route('trainer.share-links.deactivate', $foreignLink))
            ->assertNotFound();

        $this->assertTrue($foreignLink->fresh()->is_active);
    }

    public function test_trainer_share_link_list_excludes_other_trainers_links(): void
    {
        $foreignLink = ShareLink::factory()->forTrainer($this->trainerB)->create(['code' => 'foreigncodezz1']);

        $this->actingAs($this->trainerA)
            ->get(route('trainer.share-links.index'))
            ->assertOk()
            ->assertDontSee($foreignLink->code);
    }

    public function test_coach_is_limited_to_a_single_trainer(): void
    {
        $coach = CoachProfile::factory()->forTrainer($this->trainerA)->create();
        $otherCoach = CoachProfile::factory()->forTrainer($this->trainerB)->create();

        $this->actingAs($coach->user);

        $this->assertSame([$coach->id], CoachProfile::pluck('id')->all());
        $this->assertNull(CoachProfile::find($otherCoach->id));
    }

    public function test_coach_cannot_reach_trainer_admin_or_player_areas(): void
    {
        $coach = CoachProfile::factory()->forTrainer($this->trainerA)->create()->user;

        $this->actingAs($coach)->get(route('trainer.coaches.index'))->assertForbidden();
        $this->actingAs($coach)->get(route('trainer.players.index'))->assertForbidden();
        $this->actingAs($coach)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($coach)->get(route('player.dashboard'))->assertForbidden();
    }
}
