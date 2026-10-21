<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PlayerProfile;
use App\Models\Scopes\BelongsToPlayerContext;
use App\Models\TrainerPlayerAssociation;
use App\Models\User;
use App\Support\ContextResolver;
use App\Support\CurrentContext;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerContextTest extends TestCase
{
    use RefreshDatabase;

    private function connect(PlayerProfile $profile, User $trainer): TrainerPlayerAssociation
    {
        return TrainerPlayerAssociation::factory()->forTrainer($trainer)->forProfile($profile)->create();
    }

    private function contextProp(User $user): array
    {
        $response = $this->actingAs($user)->get(route('player.dashboard'));
        $response->assertOk();

        return $response->viewData('page')['props']['context'];
    }

    public function test_adult_player_sees_only_own_training_options(): void
    {
        $trainer = User::factory()->trainer()->create(['first_name' => 'Tom', 'last_name' => 'Coach']);
        $player = User::factory()->player()->create();
        $this->connect(PlayerProfile::factory()->self($player)->create(), $trainer);

        $context = $this->contextProp($player);

        $this->assertCount(1, $context['options']['yours']);
        $this->assertSame([], $context['options']['children']);
        $this->assertSame('Me → Tom Coach', $context['options']['yours'][0]['label']);
        $this->assertSame($context['options']['yours'][0], $context['current']);
    }

    public function test_parent_who_trains_sees_both_groups(): void
    {
        $trainerA = User::factory()->trainer()->create();
        $trainerB = User::factory()->trainer()->create();
        $parent = User::factory()->player()->create();
        $self = PlayerProfile::factory()->self($parent)->create();
        $child = PlayerProfile::factory()->child($parent)->create(['first_name' => 'Kid']);
        $this->connect($self, $trainerA);
        $this->connect($child, $trainerA);
        $this->connect($child, $trainerB);

        $context = $this->contextProp($parent);

        $this->assertCount(1, $context['options']['yours']);
        $this->assertCount(2, $context['options']['children']);
        $this->assertStringStartsWith('Kid', $context['options']['children'][0]['label']);
        $this->assertSame($self->id, $context['current']['player_id']);
    }

    public function test_non_training_parent_gets_children_only(): void
    {
        $trainer = User::factory()->trainer()->create();
        $parent = User::factory()->player()->create();
        $child = PlayerProfile::factory()->child($parent)->create();
        $this->connect($child, $trainer);

        $context = $this->contextProp($parent);

        $this->assertSame([], $context['options']['yours']);
        $this->assertCount(1, $context['options']['children']);
        $this->assertSame($child->id, $context['current']['player_id']);
    }

    public function test_child_login_gets_own_trainers_without_me_label(): void
    {
        $trainer = User::factory()->trainer()->create(['first_name' => 'Tom', 'last_name' => 'Coach']);
        $parent = User::factory()->player()->create();
        $childUser = User::factory()->player()->create();
        $child = PlayerProfile::factory()->child($parent)->create(['user_id' => $childUser->id]);
        $this->connect($child, $trainer);

        $context = $this->contextProp($childUser);

        $this->assertSame([], $context['options']['children']);
        $this->assertSame('Tom Coach', $context['options']['yours'][0]['label']);
    }

    public function test_inactive_association_or_trainer_is_not_offered(): void
    {
        $active = User::factory()->trainer()->create();
        $deactivated = User::factory()->trainer()->inactive()->create();
        $removed = User::factory()->trainer()->create();
        $player = User::factory()->player()->create();
        $profile = PlayerProfile::factory()->self($player)->create();
        $this->connect($profile, $active);
        $this->connect($profile, $deactivated);
        $this->connect($profile, $removed)->delete();

        $options = $this->contextProp($player)['options']['yours'];

        $this->assertSame([$active->id], array_column($options, 'trainer_id'));
    }

    public function test_player_can_switch_context_and_choice_persists(): void
    {
        $trainer = User::factory()->trainer()->create();
        $parent = User::factory()->player()->create();
        $self = PlayerProfile::factory()->self($parent)->create();
        $child = PlayerProfile::factory()->child($parent)->create();
        $this->connect($self, $trainer);
        $this->connect($child, $trainer);

        $this->actingAs($parent)
            ->post(route('player.context.update'), ['player_id' => $child->id, 'trainer_id' => $trainer->id])
            ->assertRedirect();

        $this->assertSame($child->id, session(ContextResolver::SESSION_PLAYER));

        $this->get(route('player.dashboard'))
            ->assertInertia(fn ($page) => $page->where('context.current.player_id', $child->id));
        $this->get(route('player.dashboard'))
            ->assertInertia(fn ($page) => $page->where('context.current.player_id', $child->id));
    }

    public function test_cannot_switch_to_another_users_profile(): void
    {
        $trainer = User::factory()->trainer()->create();
        $player = User::factory()->player()->create();
        $mine = PlayerProfile::factory()->self($player)->create();
        $this->connect($mine, $trainer);

        $stranger = PlayerProfile::factory()->self()->create();
        $this->connect($stranger, $trainer);
        $otherTrainer = User::factory()->trainer()->create();

        $this->actingAs($player)->get(route('player.dashboard'));

        $this->post(route('player.context.update'), ['player_id' => $stranger->id, 'trainer_id' => $trainer->id])
            ->assertSessionHasErrors('player_id');
        $this->post(route('player.context.update'), ['player_id' => $mine->id, 'trainer_id' => $otherTrainer->id])
            ->assertSessionHasErrors('player_id');
        $this->post(route('player.context.update'), [])
            ->assertSessionHasErrors(['player_id', 'trainer_id']);

        $this->assertSame($mine->id, session(ContextResolver::SESSION_PLAYER));
        $this->assertSame($trainer->id, session(ContextResolver::SESSION_TRAINER));
    }

    public function test_stale_session_context_falls_back_to_first_available(): void
    {
        $trainer = User::factory()->trainer()->create();
        $player = User::factory()->player()->create();
        $profile = PlayerProfile::factory()->self($player)->create();
        $this->connect($profile, $trainer);

        $this->actingAs($player)
            ->withSession([ContextResolver::SESSION_PLAYER => 999, ContextResolver::SESSION_TRAINER => 999])
            ->get(route('player.dashboard'))
            ->assertInertia(fn ($page) => $page->where('context.current.player_id', $profile->id));

        $this->assertSame($profile->id, session(ContextResolver::SESSION_PLAYER));
    }

    public function test_non_player_has_no_context_and_cannot_switch(): void
    {
        $trainer = User::factory()->trainer()->create();

        $this->actingAs($trainer)->get(route('trainer.dashboard'))
            ->assertInertia(fn ($page) => $page->where('context', null));
        $this->post(route('player.context.update'), ['player_id' => 1, 'trainer_id' => $trainer->id])
            ->assertForbidden();
    }

    public function test_child_cannot_reach_parent_data(): void
    {
        $trainer = User::factory()->trainer()->create();
        $parent = User::factory()->player()->create();
        $parentSelf = PlayerProfile::factory()->self($parent)->create();
        $childUser = User::factory()->player()->create();
        $child = PlayerProfile::factory()->child($parent)->create(['user_id' => $childUser->id]);
        $this->connect($parentSelf, $trainer);
        $this->connect($child, $trainer);

        $context = $this->contextProp($childUser);
        $this->assertSame([$child->id], array_column($context['options']['yours'], 'player_id'));

        $this->post(route('player.context.update'), ['player_id' => $parentSelf->id, 'trainer_id' => $trainer->id])
            ->assertSessionHasErrors('player_id');

        $model = new #[ScopedBy(BelongsToPlayerContext::class)] class extends Model
        {
            protected $table = 'trainer_player_associations';
        };

        app(CurrentContext::class)->set($child->id, $trainer->id);

        $this->assertSame([$child->id], $model::query()->pluck('player_profile_id')->all());
    }

    public function test_scope_limits_rows_to_current_context(): void
    {
        $trainer = User::factory()->trainer()->create();
        $otherTrainer = User::factory()->trainer()->create();
        $a = PlayerProfile::factory()->create();
        $b = PlayerProfile::factory()->create();
        $wanted = $this->connect($a, $trainer);
        $this->connect($b, $trainer);
        $this->connect($a, $otherTrainer);

        $model = new #[ScopedBy(BelongsToPlayerContext::class)] class extends Model
        {
            protected $table = 'trainer_player_associations';
        };

        $this->assertSame(3, $model::query()->count());

        $context = app(CurrentContext::class);
        $context->set($a->id, $trainer->id);
        $this->assertSame([$wanted->id], $model::query()->pluck('id')->all());

        $context->set(null, null);
        $this->assertSame(0, $model::query()->count());
    }
}
