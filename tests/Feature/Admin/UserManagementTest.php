<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\TrainerWelcomeMail;
use App\Models\CoachProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function trainerPayload(array $overrides = []): array
    {
        return [
            'business_name' => 'Elite Hoops',
            'first_name' => 'Jane',
            'last_name' => 'Coach',
            'email' => 'jane@example.com',
            'phone' => '+15551234567',
            ...$overrides,
        ];
    }

    public function test_super_admin_creates_a_trainer(): void
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $this->trainerPayload());

        $response->assertRedirect(route('admin.users.index'));

        $trainer = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame(UserRole::Trainer, $trainer->role);
        $this->assertSame(UserStatus::Active, $trainer->status);
        $this->assertTrue($trainer->must_change_password);
        $this->assertNull($trainer->email_verified_at);
        $this->assertSame('Elite Hoops', $trainer->trainerProfile->business_name);

        Mail::assertQueued(TrainerWelcomeMail::class, function (TrainerWelcomeMail $mail) use ($trainer): bool {
            return $mail->hasTo('jane@example.com')
                && strlen($mail->temporaryPassword) >= 12
                && Hash::check($mail->temporaryPassword, $trainer->password);
        });

        $this->assertDatabaseHas('activity_log', [
            'event' => 'trainer_created',
            'subject_id' => $trainer->id,
            'subject_type' => User::class,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_welcome_mail_contains_credentials_and_verification_link(): void
    {
        $trainer = User::factory()->trainer()->unverified()->create();

        $rendered = (new TrainerWelcomeMail($trainer, 'Temp-Pass-12345'))->render();

        $this->assertStringContainsString('Temp-Pass-12345', $rendered);
        $this->assertStringContainsString(route('login'), $rendered);
        $this->assertStringContainsString('email/verify/'.$trainer->id, $rendered);
    }

    public function test_duplicate_email_is_rejected_with_a_clear_message(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'jane@example.com']);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->trainerPayload())
            ->assertSessionHasErrors(['email' => 'A user with this email address already exists.']);

        Mail::assertNothingQueued();
        $this->assertSame(0, TrainerProfile::count());
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [])
            ->assertSessionHasErrors(['business_name', 'first_name', 'last_name', 'email']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'trainer' => ['trainer'],
            'coach' => ['coach'],
            'player' => ['player'],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_are_forbidden(string $state): void
    {
        Mail::fake();
        $user = User::factory()->{$state}()->create();
        $target = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.users.store'), $this->trainerPayload())->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.edit', $target))->assertForbidden();
        $this->actingAs($user)->put(route('admin.users.update', $target), [])->assertForbidden();

        Mail::assertNothingQueued();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_users_paginated_by_25(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->count(29)->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Users/Index')
                ->has('users.data', 25)
                ->where('users.total', 30)
                ->where('users.last_page', 2));

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 5));
    }

    public function test_index_searches_first_name_last_name_and_email(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->create(['first_name' => 'Zebulon', 'last_name' => 'Aaa', 'email' => 'a@example.com']);
        User::factory()->create(['first_name' => 'Bbb', 'last_name' => 'Quimby', 'email' => 'b@example.com']);
        User::factory()->create(['first_name' => 'Ccc', 'last_name' => 'Ddd', 'email' => 'findme@example.com']);

        foreach (['Zebulon' => 'a@example.com', 'Quimby' => 'b@example.com', 'findme' => 'findme@example.com'] as $term => $email) {
            $this->actingAs($admin)
                ->get(route('admin.users.index', ['search' => $term]))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('users.data', 1)
                    ->where('users.data.0.email', $email));
        }
    }

    public function test_index_filters_by_role_and_status_and_sorts(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->trainer()->create(['first_name' => 'Alpha']);
        User::factory()->trainer()->inactive()->create(['first_name' => 'Beta']);
        User::factory()->coach()->create(['first_name' => 'Gamma']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['role' => 'trainer']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 2));

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['role' => 'trainer', 'status' => 'inactive']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Beta '.User::where('first_name', 'Beta')->value('last_name')));

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['role' => 'trainer', 'sort' => 'name', 'direction' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page->where('users.data.0.status', 'inactive'));
    }

    public function test_index_rejects_invalid_filters(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['sort' => 'password', 'role' => 'nope']))
            ->assertSessionHasErrors(['sort', 'role']);
    }

    public function test_super_admin_edits_trainer_and_profile(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainer = User::factory()->trainer()->create(['email' => 'keep@example.com']);
        TrainerProfile::factory()->create(['user_id' => $trainer->id]);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $trainer))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Users/Edit')
                ->where('managedUser.email', 'keep@example.com')
                ->where('managedUser.role', 'trainer'));

        $this->actingAs($admin)
            ->put(route('admin.users.update', $trainer), [
                'first_name' => 'Changed',
                'last_name' => 'Name',
                'phone' => '+15559876543',
                'business_name' => 'New Biz',
                'email' => 'hacked@example.com',
                'role' => 'super_admin',
            ])
            ->assertRedirect(route('admin.users.edit', $trainer));

        $trainer->refresh();
        $this->assertSame('Changed', $trainer->first_name);
        $this->assertSame('keep@example.com', $trainer->email);
        $this->assertSame(UserRole::Trainer, $trainer->role);
        $this->assertSame('New Biz', $trainer->trainerProfile->business_name);
        $this->assertTrue(
            Activity::where('subject_id', $trainer->id)->where('event', 'updated')->where('causer_id', $admin->id)->exists(),
        );
    }

    public function test_super_admin_edits_coach_profile_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $coach = User::factory()->coach()->create();
        CoachProfile::factory()->create(['user_id' => $coach->id]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $coach), [
                'first_name' => 'Coach',
                'last_name' => 'Updated',
                'bio' => 'New bio',
                'is_public' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('New bio', CoachProfile::withoutGlobalScopes()->where('user_id', $coach->id)->value('bio'));
    }

    public function test_update_requires_role_specific_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainer = User::factory()->trainer()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $trainer), ['first_name' => 'A', 'last_name' => 'B'])
            ->assertSessionHasErrors(['business_name']);
    }
}
