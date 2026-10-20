<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\PlayerWelcomeMail;
use App\Models\PlayerProfile;
use App\Models\ShareLink;
use App\Models\TrainerPlayerAssociation;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JoinRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    private ShareLink $link;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = User::factory()->trainer()->create();
        $this->link = ShareLink::factory()->forTrainer($this->trainer)->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'first_name' => 'Pat',
            'last_name' => 'Player',
            'email' => 'pat@example.com',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
            'phone' => '+1 555 123 4567',
            'birth_date' => now()->subYears(25)->toDateString(),
            'gender' => 'male',
            ...$overrides,
        ];
    }

    public function test_guest_sees_registration_form_and_code_is_kept_in_session(): void
    {
        $this->get(route('join.show', $this->link->code))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/JoinRegister')
                ->where('code', $this->link->code)
                ->has('trainerName'))
            ->assertSessionHas('join_code', $this->link->code);
    }

    public function test_new_player_registers_and_is_associated(): void
    {
        Mail::fake();
        Notification::fake();

        $this->post(route('join.register', $this->link->code), $this->payload())
            ->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'pat@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Player, $user->role);
        $this->assertNull($user->email_verified_at);

        $profile = $user->selfPlayerProfile;
        $this->assertNotNull($profile);
        $this->assertFalse($profile->is_child);
        $this->assertSame(25, $profile->age);

        $association = TrainerPlayerAssociation::query()->where('player_profile_id', $profile->id)->firstOrFail();
        $this->assertSame($this->trainer->id, $association->trainer_id);
        $this->assertSame($this->link->id, $association->share_link_id);
        $this->assertNotNull($association->connected_at);
        $this->assertSame('active', $association->status);
        $this->assertTrue($profile->trainers->contains($this->trainer));

        $this->assertSame(1, $this->link->fresh()->uses_count);
        $this->assertSame($this->trainer->id, session('current_trainer_id'));
        $this->assertSame($profile->id, session('current_player_id'));

        Mail::assertQueued(PlayerWelcomeMail::class, fn (PlayerWelcomeMail $mail) => $mail->hasTo('pat@example.com'));
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_parent_registers_child_profile(): void
    {
        Mail::fake();

        $this->post(route('join.register', $this->link->code), $this->payload([
            'is_parent' => true,
            'player_first_name' => 'Kid',
            'player_last_name' => 'Player',
            'birth_date' => now()->subYears(10)->toDateString(),
        ]))->assertRedirect(route('verification.notice'));

        $parent = User::where('email', 'pat@example.com')->firstOrFail();
        $this->assertNull($parent->selfPlayerProfile);

        $child = $parent->childProfiles()->firstOrFail();
        $this->assertTrue($child->is_child);
        $this->assertNull($child->user_id);
        $this->assertSame('Kid', $child->first_name);
        $this->assertSame(1, TrainerPlayerAssociation::count());
        $this->assertTrue($child->trainers->contains($this->trainer));
    }

    public function test_parent_who_also_trains_gets_own_profile_and_association(): void
    {
        Mail::fake();

        $this->post(route('join.register', $this->link->code), $this->payload([
            'is_parent' => true,
            'also_trains' => true,
            'player_first_name' => 'Kid',
            'player_last_name' => 'Player',
            'birth_date' => now()->subYears(10)->toDateString(),
        ]))->assertRedirect();

        $parent = User::where('email', 'pat@example.com')->firstOrFail();
        $this->assertNotNull($parent->selfPlayerProfile);
        $this->assertSame(1, $parent->childProfiles()->count());
        $this->assertSame(2, TrainerPlayerAssociation::count());
    }

    public function test_adult_cannot_register_as_minor_and_minor_cannot_self_register(): void
    {
        $this->post(route('join.register', $this->link->code), $this->payload([
            'birth_date' => now()->subYears(12)->toDateString(),
        ]))->assertSessionHasErrors('birth_date');

        $this->post(route('join.register', $this->link->code), $this->payload([
            'is_parent' => true,
            'player_first_name' => 'Kid',
            'player_last_name' => 'Player',
            'birth_date' => now()->subYears(30)->toDateString(),
        ]))->assertSessionHasErrors('birth_date');

        $this->assertDatabaseCount('users', 1);
        $this->assertSame(0, $this->link->fresh()->uses_count);
    }

    public function test_registration_validates_required_fields_and_unique_email(): void
    {
        User::factory()->create(['email' => 'pat@example.com']);

        $this->post(route('join.register', $this->link->code), $this->payload(['password_confirmation' => 'nope']))
            ->assertSessionHasErrors(['email', 'password']);

        $this->post(route('join.register', $this->link->code), ['is_parent' => true])
            ->assertSessionHasErrors(['first_name', 'player_first_name', 'player_last_name', 'birth_date', 'gender']);
    }

    public function test_expired_inactive_and_used_links_are_rejected(): void
    {
        foreach ([
            ShareLink::factory()->forTrainer($this->trainer)->expired()->create(),
            ShareLink::factory()->forTrainer($this->trainer)->revoked()->create(),
            ShareLink::factory()->forTrainer($this->trainer)->used()->create(),
        ] as $link) {
            $this->post(route('join.register', $link->code), $this->payload())->assertStatus(410);
        }

        $this->post(route('join.register', 'missing000'), $this->payload())->assertNotFound();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_coach_link_is_rejected_for_player_registration(): void
    {
        $coachLink = ShareLink::factory()->forTrainer($this->trainer)->coach()->create();

        $this->post(route('join.register', $coachLink->code), $this->payload())->assertForbidden();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame(0, $coachLink->fresh()->uses_count);
    }

    public function test_coach_link_is_rejected_for_signed_in_player(): void
    {
        $coachLink = ShareLink::factory()->forTrainer($this->trainer)->coach()->create();
        $player = User::factory()->player()->create();
        PlayerProfile::factory()->self($player)->create();

        $this->actingAs($player)->get(route('join.show', $coachLink->code))->assertForbidden();

        $this->assertDatabaseCount('trainer_player_associations', 0);
    }

    public function test_existing_player_is_associated_with_second_trainer(): void
    {
        $player = User::factory()->player()->create();
        $profile = PlayerProfile::factory()->self($player)->create();
        $otherTrainer = User::factory()->trainer()->create();
        TrainerPlayerAssociation::factory()->forTrainer($otherTrainer)->forProfile($profile)->create();

        $this->actingAs($player)->get(route('join.show', $this->link->code))
            ->assertRedirect(route('player.dashboard'));

        $this->assertSame(2, $profile->trainerAssociations()->count());
        $this->assertSame(1, $this->link->fresh()->uses_count);
        $this->assertSame($this->trainer->id, session('current_trainer_id'));
        $this->assertSame($profile->id, session('current_player_id'));
    }

    public function test_duplicate_association_is_prevented_with_friendly_redirect(): void
    {
        $player = User::factory()->player()->create();
        $profile = PlayerProfile::factory()->self($player)->create();

        $this->actingAs($player)->get(route('join.show', $this->link->code))->assertRedirect(route('player.dashboard'));
        $this->actingAs($player)->get(route('join.show', $this->link->code))->assertRedirect(route('player.dashboard'));

        $this->assertSame(1, $profile->trainerAssociations()->count());
        $this->assertSame(1, $this->link->fresh()->uses_count);
    }

    public function test_removed_association_is_restored(): void
    {
        $player = User::factory()->player()->create();
        $profile = PlayerProfile::factory()->self($player)->create();
        $association = TrainerPlayerAssociation::factory()->forTrainer($this->trainer)->forProfile($profile)->create();
        $association->delete();

        $this->actingAs($player)->get(route('join.show', $this->link->code))->assertRedirect(route('player.dashboard'));

        $this->assertSame(1, TrainerPlayerAssociation::withTrashed()->count());
        $this->assertNull($association->fresh()->deleted_at);
    }

    public function test_parent_with_children_chooses_who_joins(): void
    {
        $parent = User::factory()->player()->create();
        $self = PlayerProfile::factory()->self($parent)->create();
        $kidA = PlayerProfile::factory()->child($parent)->create();
        $kidB = PlayerProfile::factory()->child($parent)->create();

        $this->actingAs($parent)->get(route('join.show', $this->link->code))
            ->assertRedirect(route('player.join.select', $this->link->code));

        $this->actingAs($parent)->get(route('player.join.select', $this->link->code))
            ->assertInertia(fn (Assert $page) => $page
                ->component('player/JoinSelect')
                ->has('profiles', 3));

        $this->actingAs($parent)->post(route('player.join.associate', $this->link->code), ['profile_ids' => [$kidA->id]])
            ->assertRedirect(route('player.dashboard'));

        $this->assertSame([$kidA->id], TrainerPlayerAssociation::pluck('player_profile_id')->all());
        $this->assertSame($kidA->id, session('current_player_id'));
        $this->assertSame(0, $self->trainerAssociations()->count());
        $this->assertSame(0, $kidB->trainerAssociations()->count());
    }

    public function test_selection_cannot_include_profiles_of_other_families(): void
    {
        $parent = User::factory()->player()->create();
        PlayerProfile::factory()->child($parent)->create();
        $stranger = PlayerProfile::factory()->create();

        $this->actingAs($parent)->post(route('player.join.associate', $this->link->code), ['profile_ids' => [$stranger->id]])
            ->assertSessionHasErrors('profile_ids');

        $this->assertDatabaseCount('trainer_player_associations', 0);
    }

    public function test_selection_requires_at_least_one_profile(): void
    {
        $parent = User::factory()->player()->create();
        PlayerProfile::factory()->child($parent)->create();

        $this->actingAs($parent)->post(route('player.join.associate', $this->link->code), [])
            ->assertSessionHasErrors('profile_ids');
    }

    public function test_login_after_join_link_resolves_the_link(): void
    {
        $player = User::factory()->player()->create();
        $profile = PlayerProfile::factory()->self($player)->create();

        $this->get(route('join.show', $this->link->code));
        $this->post(route('login.store'), ['email' => $player->email, 'password' => 'password'])
            ->assertRedirect(route('join.show', $this->link->code));

        $this->get(route('join.show', $this->link->code))->assertRedirect(route('player.dashboard'));
        $this->assertSame(1, $profile->trainerAssociations()->count());
    }

    public function test_non_player_account_cannot_use_player_link(): void
    {
        $this->actingAs(User::factory()->trainer()->create())
            ->get(route('join.show', $this->link->code))
            ->assertForbidden();
    }
}
