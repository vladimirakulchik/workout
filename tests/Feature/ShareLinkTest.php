<?php

namespace Tests\Feature;

use App\Actions\Trainer\CreateShareLink;
use App\Enums\ShareLinkType;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShareLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = User::factory()->trainer()->create();
    }

    public function test_player_link_is_static_per_trainer(): void
    {
        $action = new CreateShareLink;

        $first = $action->handle($this->trainer, ShareLinkType::Player);
        $second = $action->handle($this->trainer, ShareLinkType::Player);

        $this->assertTrue($first->is($second));
        $this->assertNull($first->expires_at);
        $this->assertNull($first->max_uses);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{10}$/', $first->code);
        $this->assertSame(url('/join/'.$first->code), $first->url());
    }

    public function test_codes_are_unique_across_trainers(): void
    {
        $other = User::factory()->trainer()->create();
        $action = new CreateShareLink;

        $a = $action->handle($this->trainer, ShareLinkType::Player);
        $b = $action->handle($other, ShareLinkType::Player);

        $this->assertNotSame($a->code, $b->code);
        $this->assertNotSame($a->id, $b->id);
    }

    public function test_coach_link_is_single_use_with_seven_day_expiry(): void
    {
        $link = (new CreateShareLink)->handle($this->trainer, ShareLinkType::Coach, ['target_email' => 'coach@example.com']);

        $this->assertSame(1, $link->max_uses);
        $this->assertSame('coach@example.com', $link->target_email);
        $this->assertEqualsWithDelta(now()->addDays(7)->getTimestamp(), $link->expires_at->getTimestamp(), 5);
    }

    public function test_status_and_usability(): void
    {
        $this->assertSame('pending', ShareLink::factory()->forTrainer($this->trainer)->coach()->make()->status());
        $this->assertSame('expired', ShareLink::factory()->forTrainer($this->trainer)->coach()->expired()->make()->status());
        $this->assertSame('accepted', ShareLink::factory()->forTrainer($this->trainer)->coach()->used()->make()->status());
        $this->assertSame('revoked', ShareLink::factory()->forTrainer($this->trainer)->revoked()->make()->status());
        $this->assertTrue(ShareLink::factory()->forTrainer($this->trainer)->make()->isUsable());
        $this->assertFalse(ShareLink::factory()->forTrainer($this->trainer)->coach()->used()->make()->isUsable());
        $this->assertFalse(ShareLink::factory()->forTrainer($this->trainer)->coach()->expired()->make()->isUsable());
    }

    public function test_trainer_sees_index_with_own_links_only(): void
    {
        $mine = ShareLink::factory()->forTrainer($this->trainer)->create();
        $otherTrainer = User::factory()->trainer()->create();
        ShareLink::factory()->forTrainer($otherTrainer)->coach()->create();

        $this->actingAs($this->trainer)
            ->get(route('trainer.share-links.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('trainer/ShareLinks/Index')
                ->where('playerLink.id', $mine->id)
                ->has('coachLinks', 0));
    }

    public function test_non_trainers_cannot_access_management(): void
    {
        $this->actingAs(User::factory()->player()->create())
            ->get(route('trainer.share-links.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->coach()->create())
            ->post(route('trainer.share-links.store'), ['target_email' => 'x@example.com'])
            ->assertForbidden();
    }

    public function test_trainer_creates_coach_link(): void
    {
        $this->actingAs($this->trainer)
            ->post(route('trainer.share-links.store'), ['target_email' => 'coach@example.com', 'name' => 'Sam'])
            ->assertRedirect();

        $this->assertDatabaseHas('share_links', [
            'trainer_id' => $this->trainer->id,
            'type' => 'coach',
            'target_email' => 'coach@example.com',
            'max_uses' => 1,
        ]);
    }

    public function test_coach_link_requires_valid_email(): void
    {
        $this->actingAs($this->trainer)
            ->post(route('trainer.share-links.store'), ['target_email' => 'nope'])
            ->assertSessionHasErrors('target_email');
    }

    public function test_regenerate_revokes_old_player_link(): void
    {
        $old = ShareLink::factory()->forTrainer($this->trainer)->create();

        $this->actingAs($this->trainer)->post(route('trainer.share-links.regenerate'))->assertRedirect();

        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame(1, ShareLink::query()->withoutGlobalScopes()
            ->where('trainer_id', $this->trainer->id)->where('is_active', true)->count());
    }

    public function test_trainer_deactivates_own_link(): void
    {
        $link = ShareLink::factory()->forTrainer($this->trainer)->create();

        $this->actingAs($this->trainer)->delete(route('trainer.share-links.deactivate', $link))->assertRedirect();

        $this->assertFalse($link->fresh()->is_active);
    }

    public function test_trainer_cannot_deactivate_another_trainers_link(): void
    {
        $link = ShareLink::factory()->forTrainer(User::factory()->trainer()->create())->create();

        $this->actingAs($this->trainer)->delete(route('trainer.share-links.deactivate', $link))->assertNotFound();

        $this->assertTrue($link->fresh()->is_active);
    }

    public function test_valid_link_stores_code_in_session(): void
    {
        $link = ShareLink::factory()->forTrainer($this->trainer)->create();

        $this->get(route('join.show', $link->code))
            ->assertOk()
            ->assertSessionHas('join_code', $link->code)
            ->assertInertia(fn (Assert $page) => $page->component('auth/JoinRegister')->where('trainerName', $this->trainer->name));
    }

    public function test_unusable_links_show_friendly_messages(): void
    {
        $cases = [
            'expired' => ShareLink::factory()->forTrainer($this->trainer)->coach()->expired()->create(),
            'used' => ShareLink::factory()->forTrainer($this->trainer)->coach()->used()->create(),
            'revoked' => ShareLink::factory()->forTrainer($this->trainer)->revoked()->create(),
        ];

        foreach ($cases as $link) {
            $this->get(route('join.show', $link->code))
                ->assertStatus(410)
                ->assertSessionMissing('join_code')
                ->assertInertia(fn (Assert $page) => $page->component('join/Invalid')->has('message'));
        }
    }

    public function test_unknown_code_returns_not_found_page(): void
    {
        $this->get(route('join.show', 'doesnotexist'))
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('join/Invalid'));
    }

    public function test_authenticated_trainer_can_resolve_other_trainers_link(): void
    {
        $link = ShareLink::factory()->forTrainer(User::factory()->trainer()->create())->create();

        $this->actingAs($this->trainer)->get(route('join.show', $link->code))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('join/Invalid')->has('message'));
    }
}
