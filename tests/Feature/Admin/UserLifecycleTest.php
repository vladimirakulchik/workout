<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\CoachProfile;
use App\Models\PlayerProfile;
use App\Models\ShareLink;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserDeletionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_user_cannot_log_in_and_sessions_are_killed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $player = User::factory()->player()->create(['password' => 'secret-pass-123']);
        DB::table('sessions')->insert([
            'id' => 'abc', 'user_id' => $player->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($admin)->post(route('admin.users.deactivate', $player))->assertRedirect();

        $this->assertSame(UserStatus::Inactive, $player->fresh()->status);
        $this->assertDatabaseMissing('sessions', ['user_id' => $player->id]);

        auth()->logout();
        $this->post(route('login.store'), ['email' => $player->email, 'password' => 'secret-pass-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reactivate_restores_access(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $player = User::factory()->player()->inactive()->create(['password' => 'secret-pass-123']);

        $this->actingAs($admin)->post(route('admin.users.reactivate', $player))->assertRedirect();

        $this->assertSame(UserStatus::Active, $player->fresh()->status);

        auth()->logout();
        $this->post(route('login.store'), ['email' => $player->email, 'password' => 'secret-pass-123']);
        $this->assertAuthenticatedAs($player);
    }

    public function test_delete_anonymizes_user_and_profiles_and_writes_encrypted_log(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('photos/a.jpg', 'x');
        Storage::disk('public')->put('logos/a.png', 'x');

        $admin = User::factory()->superAdmin()->create();
        $trainer = User::factory()->trainer()->create([
            'first_name' => 'Jane', 'last_name' => 'Coach', 'phone' => '+15551234567',
            'photo_path' => 'photos/a.jpg', 'remember_token' => 'tok',
        ]);
        $originalEmail = $trainer->email;
        TrainerProfile::factory()->create([
            'user_id' => $trainer->id, 'address' => '1 Main St', 'logo_path' => 'logos/a.png',
        ]);
        DB::table('sessions')->insert([
            'id' => 'abc', 'user_id' => $trainer->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $trainer), ['reason' => 'GDPR request'])
            ->assertRedirect();

        $trainer->refresh();
        $this->assertSame('Deleted', $trainer->first_name);
        $this->assertSame('User', $trainer->last_name);
        $this->assertSame('Deleted User', $trainer->displayName());
        $this->assertSame('deleted_'.$trainer->id.'@example.com', $trainer->email);
        $this->assertNull($trainer->phone);
        $this->assertNull($trainer->photo_path);
        $this->assertNull($trainer->remember_token);
        $this->assertSame(UserStatus::Deleted, $trainer->status);
        $this->assertFalse(Hash::check('password', $trainer->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $trainer->id]);
        Storage::disk('public')->assertMissing('photos/a.jpg');
        Storage::disk('public')->assertMissing('logos/a.png');

        $profile = $trainer->trainerProfile()->first();
        $this->assertNull($profile->address);
        $this->assertNull($profile->logo_path);

        $log = UserDeletionLog::firstOrFail();
        $this->assertSame($trainer->id, $log->original_user_id);
        $this->assertSame($originalEmail, $log->original_email);
        $this->assertSame($admin->id, $log->deleted_by);
        $this->assertSame('GDPR request', $log->reason);
        $this->assertSame('Jane', $log->original_data['user']['first_name']);
        $this->assertSame('1 Main St', $log->original_data['trainer_profile']['address']);

        $raw = (string) DB::table('user_deletion_logs')->value('original_data');
        $this->assertStringNotContainsString('Jane', $raw);
    }

    public function test_delete_clears_coach_and_player_profile_details_and_keeps_history_rows(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $coach = User::factory()->coach()->create();
        CoachProfile::factory()->create(['user_id' => $coach->id, 'bio' => 'Bio', 'credentials' => 'Cred']);
        $player = User::factory()->player()->create();
        $profile = PlayerProfile::factory()->self($player)->create(['school' => 'High School']);

        $this->actingAs($admin);
        $this->delete(route('admin.users.destroy', $coach), ['reason' => 'x']);
        $this->delete(route('admin.users.destroy', $player), ['reason' => 'x']);

        $this->assertNull($coach->coachProfile()->first()->bio);
        $this->assertNull($coach->coachProfile()->first()->credentials);
        $profile->refresh();
        $this->assertNull($profile->school);
        $this->assertNull($profile->birth_date);
        $this->assertNull($profile->age);
        $this->assertSame('Deleted', $profile->first_name);
        $this->assertSame($player->id, $profile->user_id);
        $this->assertDatabaseHas('users', ['id' => $player->id]);
    }

    public function test_delete_deactivates_the_trainers_share_links_only(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainer = User::factory()->trainer()->create();
        $otherTrainer = User::factory()->trainer()->create();
        $own = ShareLink::factory()->forTrainer($trainer)->create();
        $ownCoach = ShareLink::factory()->forTrainer($trainer)->coach()->create();
        $other = ShareLink::factory()->forTrainer($otherTrainer)->create();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $trainer), ['reason' => 'x']);

        $this->assertFalse($own->fresh()->is_active);
        $this->assertFalse($ownCoach->fresh()->is_active);
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_delete_requires_a_reason(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $player = User::factory()->player()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $player), [])
            ->assertSessionHasErrors('reason');

        $this->assertSame(UserStatus::Active, $player->fresh()->status);
        $this->assertSame(0, UserDeletionLog::count());
    }

    public function test_cannot_target_self_or_other_super_admins(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $other = User::factory()->superAdmin()->create();

        foreach ([$admin, $other] as $target) {
            $this->actingAs($admin)->post(route('admin.users.deactivate', $target))->assertForbidden();
            $this->actingAs($admin)->delete(route('admin.users.destroy', $target), ['reason' => 'x'])->assertForbidden();
        }

        $this->assertSame(UserStatus::Active, $other->fresh()->status);
        $this->assertSame(0, UserDeletionLog::count());
    }

    public function test_cannot_reactivate_a_deleted_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $deleted = User::factory()->player()->deleted()->create();

        $this->actingAs($admin)->post(route('admin.users.reactivate', $deleted))->assertForbidden();

        $this->assertSame(UserStatus::Deleted, $deleted->fresh()->status);
    }

    public function test_non_admins_are_forbidden(): void
    {
        $trainer = User::factory()->trainer()->create();
        $player = User::factory()->player()->create();

        $this->actingAs($trainer)->post(route('admin.users.deactivate', $player))->assertForbidden();
        $this->actingAs($trainer)->delete(route('admin.users.destroy', $player), ['reason' => 'x'])->assertForbidden();
    }
}
