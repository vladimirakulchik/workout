<?php

namespace Tests\Feature;

use App\Actions\Coach\AcceptCoachInvitation;
use App\Exceptions\CoachInvitationException;
use App\Mail\CoachInvitationMail;
use App\Mail\CoachWelcomeMail;
use App\Models\CoachProfile;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CoachInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->trainer = User::factory()->trainer()->create();
    }

    private function invitation(?User $trainer = null, string $email = 'new@example.com'): ShareLink
    {
        return ShareLink::factory()->forTrainer($trainer ?? $this->trainer)->coach()->create([
            'target_email' => $email,
            'name' => 'Sam Coach',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function registration(): array
    {
        return [
            'first_name' => 'Sam',
            'last_name' => 'Coach',
            'phone' => '+1 555 123 4567',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ];
    }

    public function test_trainer_invites_coach_and_email_is_sent(): void
    {
        $this->actingAs($this->trainer)
            ->post(route('trainer.coaches.store'), ['target_email' => 'Coach@Example.com', 'name' => 'Sam', 'message' => 'Welcome!'])
            ->assertRedirect();

        $link = ShareLink::query()->where('target_email', 'coach@example.com')->firstOrFail();
        $this->assertSame(1, $link->max_uses);

        Mail::assertQueued(CoachInvitationMail::class, fn (CoachInvitationMail $mail): bool => $mail->hasTo('coach@example.com') && $mail->link->is($link));
    }

    public function test_invite_requires_email_and_rejects_non_coach_accounts(): void
    {
        $this->actingAs($this->trainer)->post(route('trainer.coaches.store'), [])->assertSessionHasErrors('target_email');

        $player = User::factory()->player()->create();
        $this->actingAs($this->trainer)->post(route('trainer.coaches.store'), ['target_email' => $player->email])->assertSessionHasErrors('target_email');

        Mail::assertNothingQueued();
    }

    public function test_only_trainers_can_invite(): void
    {
        $this->actingAs(User::factory()->coach()->create())
            ->post(route('trainer.coaches.store'), ['target_email' => 'x@example.com'])
            ->assertForbidden();
    }

    public function test_guest_sees_registration_form_with_locked_email(): void
    {
        $link = $this->invitation();

        $this->get(route('join.show', $link->code))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/JoinCoachRegister')
                ->where('email', 'new@example.com'));
    }

    public function test_guest_registers_as_coach_and_link_is_consumed(): void
    {
        $link = $this->invitation();

        $this->post(route('join.register-coach', $link->code), $this->registration())
            ->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($user->isCoach());
        $this->assertAuthenticatedAs($user);

        $profile = CoachProfile::query()->withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame($this->trainer->id, $profile->trainer_id);
        $this->assertNotNull($profile->joined_at);

        $link->refresh();
        $this->assertSame(1, $link->uses_count);
        $this->assertFalse($link->is_active);
        $this->assertSame(ShareLink::STATUS_ACCEPTED, $link->status());

        Mail::assertQueued(CoachWelcomeMail::class, fn (CoachWelcomeMail $mail): bool => $mail->hasTo('new@example.com'));
    }

    public function test_posted_email_cannot_override_invited_email(): void
    {
        $link = $this->invitation();

        $this->post(route('join.register-coach', $link->code), $this->registration() + ['email' => 'other@example.com']);

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
    }

    public function test_link_is_single_use(): void
    {
        $link = $this->invitation();

        $this->post(route('join.register-coach', $link->code), $this->registration());
        auth()->logout();

        $this->post(route('join.register-coach', $link->code), $this->registration())->assertStatus(410);
        $this->get(route('join.show', $link->code))->assertStatus(410);
        $this->assertSame(1, User::query()->where('email', 'new@example.com')->count());
    }

    public function test_expired_link_is_rejected(): void
    {
        $link = $this->invitation();
        $link->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->get(route('join.show', $link->code))->assertStatus(410);
        $this->post(route('join.register-coach', $link->code), $this->registration())->assertStatus(410);
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_link_is_valid_until_seven_days_have_passed(): void
    {
        $link = $this->invitation();
        $link->forceFill(['expires_at' => now()->addDays(7)])->save();

        $this->travel(6)->days();
        $this->get(route('join.show', $link->code))->assertOk();

        $this->travel(2)->days();
        $this->get(route('join.show', $link->code))->assertStatus(410);
    }

    public function test_existing_coach_signed_in_is_associated(): void
    {
        $coach = User::factory()->coach()->create(['email' => 'new@example.com']);
        $link = $this->invitation();

        $this->actingAs($coach)
            ->get(route('join.show', $link->code))
            ->assertRedirect(route('coach.dashboard'));

        $this->assertSame($this->trainer->id, CoachProfile::query()->withoutGlobalScopes()->where('user_id', $coach->id)->value('trainer_id'));
        $this->assertSame(1, $link->fresh()->uses_count);
    }

    public function test_email_mismatch_is_rejected(): void
    {
        $coach = User::factory()->coach()->create(['email' => 'someone-else@example.com']);
        $link = $this->invitation();

        $this->actingAs($coach)->get(route('join.show', $link->code))->assertForbidden();

        $this->assertDatabaseMissing('coach_profiles', ['user_id' => $coach->id, 'trainer_id' => $this->trainer->id]);
        $this->assertSame(0, $link->fresh()->uses_count);
    }

    public function test_coach_active_under_another_trainer_is_rejected(): void
    {
        $other = User::factory()->trainer()->create();
        $coach = User::factory()->coach()->create(['email' => 'new@example.com']);
        CoachProfile::factory()->forTrainer($other)->create(['user_id' => $coach->id]);
        $link = $this->invitation();

        $this->actingAs($coach)
            ->get(route('join.show', $link->code))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->where('message', 'This coach is already active under another trainer.'));

        $this->assertSame($other->id, CoachProfile::query()->withoutGlobalScopes()->where('user_id', $coach->id)->value('trainer_id'));
        $this->assertSame(0, $link->fresh()->uses_count);
    }

    public function test_removed_coach_can_join_another_trainer(): void
    {
        $other = User::factory()->trainer()->create();
        $coach = User::factory()->coach()->create(['email' => 'new@example.com']);
        CoachProfile::factory()->create(['user_id' => $coach->id, 'trainer_id' => null]);
        $link = $this->invitation($other);

        $this->actingAs($coach)->get(route('join.show', $link->code))->assertRedirect(route('coach.dashboard'));

        $this->assertSame($other->id, CoachProfile::query()->withoutGlobalScopes()->where('user_id', $coach->id)->value('trainer_id'));
    }

    public function test_player_account_cannot_use_coach_link(): void
    {
        $link = $this->invitation();

        $this->actingAs(User::factory()->player()->create())->get(route('join.show', $link->code))->assertForbidden();
    }

    public function test_accept_action_rejects_non_coach_user(): void
    {
        $this->expectException(CoachInvitationException::class);

        $player = User::factory()->player()->create(['email' => 'new@example.com']);

        app(AcceptCoachInvitation::class)->handle($this->invitation(), $player);
    }

    public function test_index_lists_coaches_and_invitation_statuses(): void
    {
        $coachUser = User::factory()->coach()->create();
        CoachProfile::factory()->forTrainer($this->trainer)->create(['user_id' => $coachUser->id]);
        $this->invitation(email: 'pending@example.com');
        ShareLink::factory()->forTrainer($this->trainer)->coach()->expired()->create(['target_email' => 'expired@example.com']);
        ShareLink::factory()->forTrainer($this->trainer)->coach()->used()->create(['target_email' => 'accepted@example.com', 'is_active' => false]);
        ShareLink::factory()->forTrainer($this->trainer)->coach()->revoked()->create(['target_email' => 'revoked@example.com']);

        $this->actingAs($this->trainer)
            ->get(route('trainer.coaches.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('trainer/Coaches/Index')
                ->has('coaches', 1)
                ->has('invitations', 3)
                ->where('invitations', fn ($rows) => collect($rows)->pluck('status', 'email')->all() === [
                    'accepted@example.com' => 'accepted',
                    'expired@example.com' => 'expired',
                    'pending@example.com' => 'pending',
                ]));
    }

    public function test_resend_deactivates_old_link_and_sends_new_one(): void
    {
        $old = $this->invitation();
        $old->forceFill(['expires_at' => now()->subDay()])->save();

        $this->actingAs($this->trainer)->post(route('trainer.coaches.resend', $old))->assertRedirect();

        $old->refresh();
        $this->assertFalse($old->is_active);

        $new = ShareLink::query()->where('target_email', 'new@example.com')->where('is_active', true)->firstOrFail();
        $this->assertNotSame($old->code, $new->code);
        $this->assertTrue($new->isUsable());
        $this->assertSame('Sam Coach', $new->name);

        Mail::assertQueued(CoachInvitationMail::class, fn (CoachInvitationMail $mail): bool => $mail->link->is($new));

        $this->get(route('join.show', $old->code))->assertStatus(410);
    }

    public function test_accepted_invitation_cannot_be_resent(): void
    {
        $link = ShareLink::factory()->forTrainer($this->trainer)->coach()->used()->create(['is_active' => false]);

        $this->actingAs($this->trainer)->post(route('trainer.coaches.resend', $link))->assertStatus(422);
    }

    public function test_trainer_removes_coach_and_history_is_kept(): void
    {
        $coachUser = User::factory()->coach()->create();
        $profile = CoachProfile::factory()->forTrainer($this->trainer)->create(['user_id' => $coachUser->id]);
        $joinedAt = $profile->fresh()->joined_at;

        $this->actingAs($this->trainer)->delete(route('trainer.coaches.destroy', $profile))->assertRedirect();

        $profile->refresh();
        $this->assertNull($profile->trainer_id);
        $this->assertEquals($joinedAt, $profile->joined_at);
        $this->assertDatabaseHas('users', ['id' => $coachUser->id]);
    }

    public function test_tenant_isolation(): void
    {
        $other = User::factory()->trainer()->create();
        $otherCoach = CoachProfile::factory()->forTrainer($other)->create();
        $otherInvitation = $this->invitation($other, 'other@example.com');
        $this->invitation(email: 'mine@example.com');

        $this->actingAs($this->trainer)
            ->get(route('trainer.coaches.index'))
            ->assertInertia(fn (Assert $page) => $page->has('coaches', 0)->has('invitations', 1));

        $this->actingAs($this->trainer)->delete(route('trainer.coaches.destroy', $otherCoach))->assertNotFound();
        $this->actingAs($this->trainer)->post(route('trainer.coaches.resend', $otherInvitation))->assertNotFound();

        $this->assertSame($other->id, $otherCoach->fresh()->trainer_id);
        $this->assertTrue($otherInvitation->fresh()->is_active);
    }

    public function test_non_trainers_cannot_view_coaches_page(): void
    {
        $this->actingAs(User::factory()->coach()->create())->get(route('trainer.coaches.index'))->assertForbidden();
    }
}
