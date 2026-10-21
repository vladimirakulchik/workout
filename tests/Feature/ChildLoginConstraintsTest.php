<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\ChildWantsToJoinMail;
use App\Models\PlayerProfile;
use App\Models\ShareLink;
use App\Models\TrainerPlayerAssociation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChildLoginConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    private PlayerProfile $childProfile;

    private User $child;

    private User $trainer;

    private ShareLink $link;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = User::factory()->player()->create(['first_name' => 'Pam']);
        PlayerProfile::factory()->self($this->parent)->create();
        $this->child = User::factory()->player()->create(['first_name' => 'Kid']);
        $this->childProfile = PlayerProfile::factory()->child($this->parent)->create([
            'user_id' => $this->child->id,
            'first_name' => 'Kid',
        ]);
        $this->trainer = User::factory()->trainer()->create(['first_name' => 'Tom', 'last_name' => 'Coach']);
        $this->link = ShareLink::factory()->forTrainer($this->trainer)->create();
    }

    public function test_restricted_routes_are_forbidden_for_child(): void
    {
        $this->actingAs($this->child);
        $profile = $this->childProfile->id;

        $this->get(route('player.family.index'))->assertForbidden();
        $this->get(route('player.family.create'))->assertForbidden();
        $this->post(route('player.family.store'))->assertForbidden();
        $this->get(route('player.family.edit', $profile))->assertForbidden();
        $this->put(route('player.family.update', $profile))->assertForbidden();
        $this->delete(route('player.family.destroy', $profile))->assertForbidden();
        $this->post(route('player.family.trainers.store', $profile))->assertForbidden();
        $this->delete(route('player.family.trainers.destroy', [$profile, $this->trainer->id]))->assertForbidden();
        $this->get(route('player.join.select', $this->link->code))->assertForbidden();
        $this->post(route('player.join.associate', $this->link->code))->assertForbidden();
    }

    public function test_parent_still_reaches_family_pages(): void
    {
        $this->actingAs($this->parent)->get(route('player.family.index'))->assertOk();
    }

    public function test_child_navigation_is_limited(): void
    {
        $titles = fn (User $user): array => collect(
            $this->actingAs($user)->get(route('player.dashboard'))->viewData('page')['props']['navigation'],
        )->pluck('title')->all();

        $this->assertSame(['Dashboard', 'Best Times', 'Profile'], $titles($this->child));
        $this->assertContains('Players / Family', $titles($this->parent));
    }

    public function test_child_profile_edit_only_changes_photo(): void
    {
        Storage::fake('public');
        $this->child->update(['phone' => '+15550000001']);

        $this->actingAs($this->child)
            ->patch(route('profile.update'), [
                'first_name' => 'Hacked',
                'last_name' => 'Name',
                'phone' => '+15559999999',
                'photo' => UploadedFile::fake()->image('me.png', 200, 200),
            ])
            ->assertRedirect(route('profile.edit'));

        $this->child->refresh();
        $this->assertSame('Kid', $this->child->first_name);
        $this->assertSame('+15550000001', $this->child->phone);
        $this->assertNotNull($this->child->photo_path);
    }

    public function test_child_profile_page_is_flagged_restricted(): void
    {
        $this->actingAs($this->child)->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('restricted', true));
    }

    public function test_dashboard_exposes_read_only_wallet_placeholder(): void
    {
        $this->actingAs($this->child)->get(route('player.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('wallet.read_only', true)
                ->where('wallet.can_purchase', false));
    }

    public function test_child_opening_join_link_is_blocked_and_parent_is_mailed(): void
    {
        Mail::fake();

        $this->actingAs($this->child)->get(route('join.show', $this->link->code))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('player/JoinBlocked')
                ->where('trainerName', 'Tom Coach'));

        Mail::assertQueuedCount(1);
        Mail::assertQueued(ChildWantsToJoinMail::class, function (ChildWantsToJoinMail $mail): bool {
            $mail->assertHasSubject("Kid wants to join Tom Coach's program");
            $mail->assertSeeInHtml(route('join.show', $this->link->code));
            $mail->assertSeeInHtml('Review Registration');

            return $mail->hasTo($this->parent->email);
        });
        $this->assertSame(0, TrainerPlayerAssociation::query()->withoutGlobalScopes()->count());
    }

    public function test_notification_is_throttled_to_one_per_hour_per_link(): void
    {
        Mail::fake();

        $this->actingAs($this->child);
        $this->get(route('join.show', $this->link->code))->assertOk();
        $this->get(route('join.show', $this->link->code))->assertOk();

        Mail::assertQueuedCount(1);

        $otherLink = ShareLink::factory()->forTrainer($this->trainer)->create();
        $this->get(route('join.show', $otherLink->code))->assertOk();

        Mail::assertQueuedCount(2);
    }

    public function test_parent_can_finish_registration_via_link(): void
    {
        $this->actingAs($this->parent)
            ->post(route('player.join.associate', $this->link->code), ['profile_ids' => [$this->childProfile->id]])
            ->assertRedirect(route('player.dashboard'));

        $this->assertTrue($this->childProfile->trainers()->whereKey($this->trainer->id)->exists());
    }
}
