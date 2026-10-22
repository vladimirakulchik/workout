<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\ChildPurchaseApproval;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function adminRoutes(): array
    {
        return [
            'dashboard' => ['admin.dashboard'],
            'users' => ['admin.users.index'],
            'impersonation history' => ['admin.impersonation-history'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_admin_routes_are_forbidden_for_every_other_role(string $routeName): void
    {
        $users = [
            User::factory()->trainer()->create(),
            User::factory()->coach()->create(),
            User::factory()->player()->create(),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->get(route($routeName))->assertForbidden();
        }
    }

    public function test_admin_routes_require_authentication(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_player_cannot_reach_trainer_or_coach_areas(): void
    {
        $player = User::factory()->player()->create();

        $this->actingAs($player)->get(route('trainer.dashboard'))->assertForbidden();
        $this->actingAs($player)->get(route('trainer.share-links.index'))->assertForbidden();
        $this->actingAs($player)->get(route('coach.dashboard'))->assertForbidden();
    }

    public function test_admin_only_mutations_are_forbidden_for_a_trainer(): void
    {
        $trainer = User::factory()->trainer()->create();
        $target = User::factory()->player()->create();

        $this->actingAs($trainer)->post(route('admin.users.deactivate', $target))->assertForbidden();
        $this->actingAs($trainer)->delete(route('admin.users.destroy', $target), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($trainer)->post(route('admin.users.store'), [])->assertForbidden();

        $this->assertSame('active', $target->fresh()->status->value);
    }

    public function test_player_cannot_switch_to_another_players_profile(): void
    {
        $player = User::factory()->player()->create();
        PlayerProfile::factory()->self($player)->create();
        $foreign = PlayerProfile::factory()->create();

        $this->actingAs($player)
            ->post(route('player.context.update'), ['player_id' => $foreign->id])
            ->assertSessionMissing('current_player_id');
    }

    public function test_parent_cannot_manage_another_parents_child(): void
    {
        $parent = User::factory()->player()->create();
        PlayerProfile::factory()->self($parent)->create();
        $foreignChild = PlayerProfile::factory()->child()->create();

        $this->actingAs($parent)->get(route('player.family.edit', $foreignChild))->assertForbidden();
        $this->actingAs($parent)->delete(route('player.family.destroy', $foreignChild))->assertForbidden();

        $this->assertModelExists($foreignChild);
    }

    public function test_parent_cannot_decide_another_parents_approval(): void
    {
        $parent = User::factory()->player()->create();
        PlayerProfile::factory()->self($parent)->create();
        $approval = ChildPurchaseApproval::factory()->create();

        $this->actingAs($parent)->post(route('player.approvals.approve', $approval))->assertForbidden();

        $this->assertSame('pending', $approval->fresh()->status->value);
    }
}
