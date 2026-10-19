<?php

namespace Tests\Feature\Settings;

use App\Models\CoachProfile;
use App\Models\PlayerProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_common_fields_can_be_updated_and_email_is_immutable()
    {
        $user = User::factory()->superAdmin()->create(['email' => 'original@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'Test',
                'last_name' => 'User',
                'phone' => '+1 555 123 4567',
                'email' => 'changed@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('+1 555 123 4567', $user->phone);
        $this->assertSame('original@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_phone_must_look_like_a_phone_number()
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['first_name' => 'A', 'last_name' => 'B', 'phone' => 'call me'])
            ->assertSessionHasErrors('phone');
    }

    public function test_player_fields_persist_and_skill_level_is_read_only()
    {
        $profile = PlayerProfile::factory()->create(['skill_level' => 'Advanced']);
        $user = $profile->user;

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'Pat',
                'last_name' => 'Player',
                'school' => 'Central High',
                'jersey_number' => '23',
                'emergency_contact_name' => 'Mom',
                'emergency_contact_phone' => '+15551234567',
                'skill_level' => 'Beginner',
            ])
            ->assertSessionHasNoErrors();

        $profile->refresh();

        $this->assertSame('Central High', $profile->school);
        $this->assertSame('23', $profile->jersey_number);
        $this->assertSame('Mom', $profile->emergency_contact_name);
        $this->assertSame('+15551234567', $profile->emergency_contact_phone);
        $this->assertSame('Advanced', $profile->skill_level);
    }

    public function test_player_requires_emergency_contact_name()
    {
        $user = PlayerProfile::factory()->create()->user;

        $this->actingAs($user)
            ->patch(route('profile.update'), ['first_name' => 'A', 'last_name' => 'B'])
            ->assertSessionHasErrors('emergency_contact_name');
    }

    public function test_coach_fields_persist_without_an_attached_trainer()
    {
        $user = User::factory()->coach()->create();
        $profile = CoachProfile::factory()->create(['user_id' => $user->id, 'is_public' => false]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'Coach',
                'last_name' => 'Carter',
                'bio' => 'Ten years of coaching.',
                'credentials' => 'BSc Sport Science',
                'certifications' => 'UEFA B',
                'is_public' => '1',
            ])
            ->assertSessionHasNoErrors();

        $profile = CoachProfile::withoutGlobalScopes()->findOrFail($profile->id);

        $this->assertSame('Ten years of coaching.', $profile->bio);
        $this->assertSame('BSc Sport Science', $profile->credentials);
        $this->assertSame('UEFA B', $profile->certifications);
        $this->assertTrue($profile->is_public);
    }

    public function test_trainer_fields_persist()
    {
        $profile = TrainerProfile::factory()->create();

        $this->actingAs($profile->user)
            ->patch(route('profile.update'), [
                'first_name' => 'Tina',
                'last_name' => 'Trainer',
                'business_name' => 'Elite Training',
                'address' => '1 Main St',
                'website' => 'https://elite.example.com',
                'description' => 'We train.',
            ])
            ->assertSessionHasNoErrors();

        $profile->refresh();

        $this->assertSame('Elite Training', $profile->business_name);
        $this->assertSame('1 Main St', $profile->address);
        $this->assertSame('https://elite.example.com', $profile->website);
        $this->assertSame('We train.', $profile->description);
    }

    public function test_trainer_requires_business_name_and_valid_website()
    {
        $user = TrainerProfile::factory()->create()->user;

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'A',
                'last_name' => 'B',
                'website' => 'not a url',
            ])
            ->assertSessionHasErrors(['business_name', 'website']);
    }

    public function test_photo_is_resized_stored_and_old_photo_removed()
    {
        Storage::fake('public');
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'A',
                'last_name' => 'B',
                'photo' => UploadedFile::fake()->image('first.png', 800, 600),
            ])
            ->assertSessionHasNoErrors();

        $first = $user->refresh()->photo_path;
        $this->assertStringStartsWith("photos/{$user->id}/", $first);
        Storage::disk('public')->assertExists($first);
        Storage::disk('public')->assertExists(str_replace('.webp', '_thumb.webp', $first));

        $size = getimagesizefromstring(Storage::disk('public')->get($first));
        $this->assertSame([400, 400], [$size[0], $size[1]]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'A',
                'last_name' => 'B',
                'photo' => UploadedFile::fake()->image('second.jpg', 500, 500),
            ])
            ->assertSessionHasNoErrors();

        $second = $user->refresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertMissing(str_replace('.webp', '_thumb.webp', $first));
        Storage::disk('public')->assertExists($second);
        $this->assertStringContainsString($second, $user->photoUrl());
    }

    public function test_photo_url_falls_back_to_default_avatar()
    {
        $this->assertStringEndsWith('images/default-avatar.svg', User::factory()->make()->photoUrl());
    }

    public function test_photo_must_be_an_allowed_image_type()
    {
        Storage::fake('public');
        $user = User::factory()->superAdmin()->create();

        foreach ([UploadedFile::fake()->image('a.gif'), UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')] as $file) {
            $this->actingAs($user)
                ->patch(route('profile.update'), ['first_name' => 'A', 'last_name' => 'B', 'photo' => $file])
                ->assertSessionHasErrors('photo');
        }

        $this->assertNull($user->refresh()->photo_path);
    }

    public function test_photo_must_not_exceed_two_megabytes()
    {
        Storage::fake('public');
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'A',
                'last_name' => 'B',
                'photo' => UploadedFile::fake()->image('big.png')->size(2049),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->refresh()->photo_path);
    }

    public function test_account_deletion_route_is_removed()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/settings/profile')->assertStatus(405);

        $this->assertNotNull($user->fresh());
    }
}
