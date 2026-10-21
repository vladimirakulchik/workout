<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\PlayerProfile;
use App\Models\TrainerPlayerAssociation;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChildProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = User::factory()->player()->create(['phone' => '+15551234567']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'first_name' => 'Mia',
            'last_name' => 'Parker',
            'birth_date' => now()->subYears(10)->toDateString(),
            'gender' => 'female',
            ...$overrides,
        ];
    }

    private function connect(User $trainer, ?PlayerProfile $profile = null): PlayerProfile
    {
        $profile ??= PlayerProfile::factory()->self($this->parent)->create();
        TrainerPlayerAssociation::factory()->forTrainer($trainer)->forProfile($profile)->create();

        return $profile;
    }

    public function test_parent_creates_unassociated_child_without_trainers(): void
    {
        $this->actingAs($this->parent)
            ->post(route('player.family.store'), $this->payload(['school' => 'Oak School']))
            ->assertRedirect(route('player.family.index'));

        $child = PlayerProfile::where('parent_user_id', $this->parent->id)->sole();
        $this->assertTrue($child->is_child);
        $this->assertNull($child->user_id);
        $this->assertSame('Oak School', $child->school);
        $this->assertSame(0, TrainerPlayerAssociation::query()->withoutGlobalScopes()->count());
    }

    public function test_index_lists_only_own_children(): void
    {
        PlayerProfile::factory()->child($this->parent)->create(['first_name' => 'Mine']);
        PlayerProfile::factory()->child()->create(['first_name' => 'Stranger']);

        $this->actingAs($this->parent)->get(route('player.family.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('player/Family/Index')
                ->has('children', 1)
                ->where('children.0.name', fn (string $name) => str_starts_with($name, 'Mine')));
    }

    public function test_birth_date_must_give_age_between_one_and_eighteen(): void
    {
        $this->actingAs($this->parent);

        foreach ([now()->toDateString(), now()->subMonths(6)->toDateString(), now()->subYears(19)->toDateString(), now()->subYears(30)->toDateString()] as $date) {
            $this->post(route('player.family.store'), $this->payload(['birth_date' => $date]))
                ->assertSessionHasErrors('birth_date');
        }

        foreach ([now()->subYears(1)->toDateString(), now()->subYears(18)->toDateString()] as $date) {
            $this->post(route('player.family.store'), $this->payload(['birth_date' => $date, 'first_name' => 'Kid'.$date]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, PlayerProfile::count());
    }

    public function test_gender_and_names_are_required_and_photo_must_be_image(): void
    {
        $this->actingAs($this->parent)
            ->post(route('player.family.store'), ['birth_date' => now()->subYears(8)->toDateString(), 'photo' => UploadedFile::fake()->create('x.pdf', 10)])
            ->assertSessionHasErrors(['first_name', 'last_name', 'gender', 'photo']);
    }

    public function test_single_trainer_can_be_accepted_or_declined(): void
    {
        $trainer = User::factory()->trainer()->create();
        $this->connect($trainer);
        $this->actingAs($this->parent);

        $this->post(route('player.family.store'), $this->payload(['first_name' => 'Yes', 'trainer_ids' => [$trainer->id]]));
        $this->post(route('player.family.store'), $this->payload(['first_name' => 'Nope', 'trainer_ids' => []]));

        $yes = PlayerProfile::where('first_name', 'Yes')->sole();
        $no = PlayerProfile::where('first_name', 'Nope')->sole();

        $this->assertSame([$trainer->id], $yes->trainers()->pluck('users.id')->all());
        $this->assertCount(0, $no->trainers);
    }

    public function test_multiple_trainers_can_be_selected_individually(): void
    {
        $a = User::factory()->trainer()->create();
        $b = User::factory()->trainer()->create();
        $profile = $this->connect($a);
        $this->connect($b, $profile);

        $this->actingAs($this->parent)
            ->get(route('player.family.create'))
            ->assertInertia(fn ($page) => $page->has('trainers', 2));

        $this->post(route('player.family.store'), $this->payload(['trainer_ids' => [$b->id]]));

        $child = PlayerProfile::where('is_child', true)->sole();
        $this->assertSame([$b->id], $child->trainers()->pluck('users.id')->all());
    }

    public function test_cannot_select_a_trainer_the_parent_is_not_connected_to(): void
    {
        $stranger = User::factory()->trainer()->create();

        $this->actingAs($this->parent)
            ->post(route('player.family.store'), $this->payload(['trainer_ids' => [$stranger->id]]))
            ->assertSessionHasErrors('trainer_ids.0');

        $this->assertSame(0, PlayerProfile::count());
    }

    public function test_similar_child_triggers_confirm_step_instead_of_creating(): void
    {
        PlayerProfile::factory()->child($this->parent)->create([
            'first_name' => 'Michael',
            'birth_date' => now()->subYears(10)->toDateString(),
        ]);
        $this->actingAs($this->parent);

        $this->post(route('player.family.store'), $this->payload(['first_name' => 'Micheal']))
            ->assertSessionHasErrors('duplicate');
        $this->assertSame(1, PlayerProfile::count());

        $this->post(route('player.family.store'), $this->payload(['first_name' => 'Micheal', 'confirm' => 1]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, PlayerProfile::count());
    }

    public function test_same_name_with_other_birth_year_is_not_a_duplicate(): void
    {
        PlayerProfile::factory()->child($this->parent)->create([
            'first_name' => 'Mia',
            'birth_date' => now()->subYears(15)->toDateString(),
        ]);

        $this->actingAs($this->parent)
            ->post(route('player.family.store'), $this->payload())
            ->assertSessionHasNoErrors();
        $this->assertSame(2, PlayerProfile::count());
    }

    public function test_child_login_is_created_unverified_with_parent_phone(): void
    {
        Notification::fake();

        $this->actingAs($this->parent)
            ->post(route('player.family.store'), $this->payload([
                'has_login' => 1,
                'email' => 'Mia@Example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]))
            ->assertSessionHasNoErrors();

        $login = User::where('email', 'mia@example.com')->sole();
        $this->assertSame('player', $login->role->value);
        $this->assertSame('+15551234567', $login->phone);
        $this->assertNull($login->email_verified_at);
        $this->assertSame(UserStatus::Active, $login->status);

        $child = PlayerProfile::where('parent_user_id', $this->parent->id)->sole();
        $this->assertSame($login->id, $child->user_id);
        Notification::assertSentTo($login, VerifyEmail::class);
    }

    public function test_child_login_requires_unique_email_and_password(): void
    {
        $this->actingAs($this->parent)
            ->post(route('player.family.store'), $this->payload([
                'has_login' => 1,
                'email' => $this->parent->email,
                'password' => 'x',
            ]))
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertSame(0, PlayerProfile::count());
    }

    public function test_photo_is_stored_for_child(): void
    {
        Storage::fake('public');

        $this->actingAs($this->parent)
            ->post(route('player.family.store'), $this->payload(['photo' => UploadedFile::fake()->image('kid.png', 600, 600)]))
            ->assertSessionHasNoErrors();

        $child = PlayerProfile::sole();
        $this->assertNotNull($child->photo_path);
        Storage::disk('public')->assertExists($child->photo_path);
    }

    public function test_parent_updates_child_and_token_spending_switch(): void
    {
        $child = PlayerProfile::factory()->child($this->parent)->create();

        $this->actingAs($this->parent)
            ->put(route('player.family.update', $child), $this->payload(['first_name' => 'Zed', 'allow_token_spending' => 1]))
            ->assertRedirect(route('player.family.index'));

        $child->refresh();
        $this->assertSame('Zed', $child->first_name);
        $this->assertTrue($child->allow_token_spending);
    }

    public function test_deleting_child_removes_profile_and_deactivates_login(): void
    {
        $login = User::factory()->player()->create();
        $child = PlayerProfile::factory()->child($this->parent)->create(['user_id' => $login->id]);

        $this->actingAs($this->parent)
            ->delete(route('player.family.destroy', $child))
            ->assertRedirect(route('player.family.index'));

        $this->assertModelMissing($child);
        $this->assertSame(UserStatus::Inactive, $login->refresh()->status);
    }

    public function test_other_parent_is_forbidden(): void
    {
        $child = PlayerProfile::factory()->child($this->parent)->create();
        $other = User::factory()->player()->create();
        $this->actingAs($other);

        $this->get(route('player.family.edit', $child))->assertForbidden();
        $this->put(route('player.family.update', $child), $this->payload())->assertForbidden();
        $this->delete(route('player.family.destroy', $child))->assertForbidden();
        $this->assertModelExists($child);
    }

    public function test_child_login_and_non_players_cannot_add_children(): void
    {
        $login = User::factory()->player()->create();
        PlayerProfile::factory()->child($this->parent)->create(['user_id' => $login->id]);

        $this->actingAs($login)->post(route('player.family.store'), $this->payload())->assertForbidden();

        $this->actingAs(User::factory()->trainer()->create())
            ->get(route('player.family.index'))
            ->assertForbidden();
    }
}
