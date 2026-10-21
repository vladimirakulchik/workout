<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ChildRemovedFromTrainer;
use App\Models\PlayerProfile;
use App\Models\ShareLink;
use App\Models\TrainerPlayerAssociation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChildTrainerAssociationTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    private PlayerProfile $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = User::factory()->player()->create();
        $this->child = PlayerProfile::factory()->child($this->parent)->create();
    }

    private function connect(User $trainer, PlayerProfile $profile): TrainerPlayerAssociation
    {
        return TrainerPlayerAssociation::factory()->forTrainer($trainer)->forProfile($profile)->create();
    }

    public function test_family_page_lists_trainers_with_connected_date_and_my_trainers(): void
    {
        $trainer = User::factory()->trainer()->create();
        $this->connect($trainer, $this->child);

        $this->actingAs($this->parent)
            ->get(route('player.family.index'))
            ->assertInertia(fn ($page) => $page
                ->where('children.0.trainers.0.id', $trainer->id)
                ->where('children.0.trainers.0.connected_at', now()->toDateString())
                ->where('myTrainers.0.id', $trainer->id));
    }

    public function test_add_via_full_share_link_url(): void
    {
        $link = ShareLink::factory()->create();

        $this->actingAs($this->parent)
            ->post(route('player.family.trainers.store', $this->child), ['code' => $link->url()])
            ->assertRedirect(route('player.family.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame([$link->trainer_id], $this->child->trainers()->pluck('users.id')->all());
        $this->assertSame(1, $link->fresh()->uses_count);
    }

    public function test_add_via_bare_code(): void
    {
        $link = ShareLink::factory()->create();

        $this->actingAs($this->parent)
            ->post(route('player.family.trainers.store', $this->child), ['code' => $link->code])
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $this->child->trainers);
    }

    public function test_invalid_unusable_or_coach_links_are_rejected(): void
    {
        $inputs = [
            'not a link!',
            'https://example.com/join/unknown123',
            ShareLink::factory()->expired()->create()->code,
            ShareLink::factory()->revoked()->create()->code,
            ShareLink::factory()->coach()->create()->code,
        ];

        foreach ($inputs as $input) {
            $this->actingAs($this->parent)
                ->post(route('player.family.trainers.store', $this->child), ['code' => $input])
                ->assertSessionHasErrors('code');
        }

        $this->assertCount(0, $this->child->trainers);
    }

    public function test_add_from_my_trainers_via_another_child(): void
    {
        $trainer = User::factory()->trainer()->create();
        $other = PlayerProfile::factory()->child($this->parent)->create();
        $this->connect($trainer, $other);

        $this->actingAs($this->parent)
            ->post(route('player.family.trainers.store', $this->child), ['trainer_id' => $trainer->id])
            ->assertSessionHasNoErrors();

        $this->assertSame([$trainer->id], $this->child->trainers()->pluck('users.id')->all());
    }

    public function test_cannot_add_trainer_outside_my_trainers(): void
    {
        $stranger = User::factory()->trainer()->create();

        $this->actingAs($this->parent)
            ->post(route('player.family.trainers.store', $this->child), ['trainer_id' => $stranger->id])
            ->assertSessionHasErrors('trainer_id');

        $this->assertCount(0, $this->child->trainers);
    }

    public function test_adding_an_already_connected_trainer_is_rejected(): void
    {
        $link = ShareLink::factory()->create();
        $this->connect($link->trainer, $this->child);

        $this->actingAs($this->parent)
            ->post(route('player.family.trainers.store', $this->child), ['code' => $link->code])
            ->assertSessionHasErrors('code');
    }

    public function test_remove_soft_deletes_association_and_dispatches_event(): void
    {
        Event::fake([ChildRemovedFromTrainer::class]);
        $trainer = User::factory()->trainer()->create();
        $association = $this->connect($trainer, $this->child);

        $this->actingAs($this->parent)
            ->delete(route('player.family.trainers.destroy', [$this->child, $trainer]))
            ->assertRedirect(route('player.family.index'));

        $this->assertSoftDeleted($association);
        $this->assertCount(0, $this->child->trainers);
        Event::assertDispatched(
            ChildRemovedFromTrainer::class,
            fn (ChildRemovedFromTrainer $event): bool => $event->child->is($this->child) && $event->trainer->is($trainer),
        );
    }

    public function test_removed_child_is_gone_from_trainer_roster_and_my_trainers(): void
    {
        $trainer = User::factory()->trainer()->create();
        $this->connect($trainer, $this->child);

        $this->actingAs($this->parent)->delete(route('player.family.trainers.destroy', [$this->child, $trainer]));

        $this->actingAs($trainer);
        $this->assertSame(0, TrainerPlayerAssociation::query()->count());
        $this->assertSame([], $this->parent->connectedTrainerIds());
    }

    public function test_re_adding_restores_the_soft_deleted_association(): void
    {
        $link = ShareLink::factory()->create();
        $association = $this->connect($link->trainer, $this->child);
        $association->delete();

        $this->actingAs($this->parent)
            ->post(route('player.family.trainers.store', $this->child), ['code' => $link->code])
            ->assertSessionHasNoErrors();

        $this->assertNull($association->fresh()->deleted_at);
        $this->assertSame(1, TrainerPlayerAssociation::withTrashed()->withoutGlobalScopes()->count());
    }

    public function test_foreign_child_is_forbidden(): void
    {
        $stranger = User::factory()->player()->create();
        $trainer = User::factory()->trainer()->create();
        $this->connect($trainer, $this->child);
        $link = ShareLink::factory()->create();

        $this->actingAs($stranger)
            ->post(route('player.family.trainers.store', $this->child), ['code' => $link->code])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->delete(route('player.family.trainers.destroy', [$this->child, $trainer]))
            ->assertForbidden();

        $this->assertCount(1, $this->child->trainers);
    }
}
