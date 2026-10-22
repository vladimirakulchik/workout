<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CoachProfile;
use App\Models\PlayerProfile;
use App\Models\TrainerPlayerAssociation;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Support\ContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrainerBrandingTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->trainer = $this->makeTrainer();
    }

    private function makeTrainer(?string $color = null): User
    {
        $trainer = User::factory()->trainer()->create();
        TrainerProfile::factory()->create(['user_id' => $trainer->id, 'brand_color' => $color, 'logo_path' => null]);

        return $trainer;
    }

    private function save(array $data): TestResponse
    {
        return $this->actingAs($this->trainer)->post(route('trainer.branding.update'), $data);
    }

    private function brandingProp(User $user, string $route): array
    {
        return $this->actingAs($user)->get(route($route))->assertOk()->viewData('page')['props']['branding'];
    }

    public function test_trainer_can_view_branding_page(): void
    {
        $this->actingAs($this->trainer)->get(route('trainer.branding.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('trainer/Branding')->where('current.color', null));
    }

    public function test_only_trainers_can_access_branding(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)->get(route('trainer.branding.edit'))->assertForbidden();
        $this->actingAs($coach)->post(route('trainer.branding.update'), ['brand_color' => '#112233'])->assertForbidden();
        $this->actingAs($coach)->delete(route('trainer.branding.destroy'))->assertForbidden();
    }

    public function test_color_is_saved_and_normalised(): void
    {
        $this->save(['brand_color' => '#ff8800'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('#FF8800', $this->trainer->trainerProfile->fresh()->brand_color);
    }

    #[DataProvider('invalidColors')]
    public function test_invalid_color_is_rejected(string $color): void
    {
        $this->save(['brand_color' => $color])->assertSessionHasErrors('brand_color');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidColors(): array
    {
        return ['no hash' => ['FF8800'], 'short' => ['#FFF'], 'non hex' => ['#GGGGGG'], 'too long' => ['#FF880011']];
    }

    public function test_raster_logo_is_resized_to_200_pixels(): void
    {
        $this->save(['logo' => UploadedFile::fake()->image('logo.png', 800, 400)])->assertSessionHasNoErrors();

        $path = $this->trainer->trainerProfile->fresh()->logo_path;

        Storage::disk('public')->assertExists($path);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(200, $width);
        $this->assertSame(100, $height);
    }

    public function test_small_logo_is_not_enlarged_and_replacing_deletes_previous_file(): void
    {
        $this->save(['logo' => UploadedFile::fake()->image('a.jpg', 50, 50)]);
        $first = $this->trainer->trainerProfile->fresh()->logo_path;

        $this->save(['logo' => UploadedFile::fake()->image('b.jpg', 60, 60)]);
        $second = $this->trainer->trainerProfile->fresh()->logo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        [$width] = getimagesizefromstring(Storage::disk('public')->get($second));
        $this->assertSame(60, $width);
    }

    public function test_logo_type_and_size_are_validated(): void
    {
        $this->save(['logo' => UploadedFile::fake()->create('logo.gif', 10, 'image/gif')])->assertSessionHasErrors('logo');
        $this->save(['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])->assertSessionHasErrors('logo');
        $this->save(['logo' => UploadedFile::fake()->image('big.png', 100, 100)->size(2049)])->assertSessionHasErrors('logo');
    }

    public function test_safe_svg_is_stored_as_is(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#f00"/></svg>';

        $this->save(['logo' => UploadedFile::fake()->createWithContent('logo.svg', $svg)])->assertSessionHasNoErrors();

        $this->assertSame($svg, Storage::disk('public')->get($this->trainer->trainerProfile->fresh()->logo_path));
    }

    #[DataProvider('unsafeSvgs')]
    public function test_unsafe_svg_is_rejected(string $svg): void
    {
        $this->save(['logo' => UploadedFile::fake()->createWithContent('logo.svg', $svg)])->assertSessionHasErrors('logo');

        $this->assertNull($this->trainer->trainerProfile->fresh()->logo_path);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeSvgs(): array
    {
        $open = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">';

        return [
            'script element' => [$open.'<script>alert(1)</script></svg>'],
            'event handler' => [$open.'<rect onclick="alert(1)" width="1" height="1"/></svg>'],
            'onload on root' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>'],
            'javascript href' => [$open.'<a xlink:href="javascript:alert(1)"><rect width="1" height="1"/></a></svg>'],
            'foreignObject' => [$open.'<foreignObject><div/></foreignObject></svg>'],
            'external image' => [$open.'<image href="https://evil.test/x.png"/></svg>'],
            'external use' => [$open.'<use xlink:href="https://evil.test/x.svg#a"/></svg>'],
            'doctype entity' => ['<!DOCTYPE svg [<!ENTITY x "y">]>'.$open.'</svg>'],
            'not xml' => ['<svg><rect></svg>'],
        ];
    }

    public function test_reset_clears_branding_and_deletes_logo(): void
    {
        $this->save(['logo' => UploadedFile::fake()->image('logo.png', 100, 100), 'brand_color' => '#123456']);
        $path = $this->trainer->trainerProfile->fresh()->logo_path;

        $this->actingAs($this->trainer)->delete(route('trainer.branding.destroy'))->assertRedirect();

        $profile = $this->trainer->trainerProfile->fresh();
        $this->assertNull($profile->logo_path);
        $this->assertNull($profile->brand_color);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_trainer_sees_own_branding_in_shared_props(): void
    {
        $this->save(['logo' => UploadedFile::fake()->image('logo.png', 100, 100), 'brand_color' => '#123456']);

        $branding = $this->brandingProp($this->trainer, 'trainer.dashboard');

        $this->assertSame('#123456', $branding['color']);
        $this->assertSame(Storage::disk('public')->url($this->trainer->trainerProfile->fresh()->logo_path), $branding['logo_url']);
    }

    public function test_coach_resolves_branding_of_their_trainer_only(): void
    {
        $other = $this->makeTrainer('#ABCDEF');
        $this->trainer->trainerProfile->update(['brand_color' => '#123456']);
        $coach = User::factory()->coach()->create();
        CoachProfile::factory()->forTrainer($this->trainer)->create(['user_id' => $coach->id]);

        $branding = $this->brandingProp($coach, 'coach.dashboard');

        $this->assertSame('#123456', $branding['color']);
        $this->assertNotSame($other->trainerProfile->brand_color, $branding['color']);
    }

    public function test_player_resolves_branding_of_current_context_trainer(): void
    {
        $other = $this->makeTrainer('#ABCDEF');
        $this->trainer->trainerProfile->update(['brand_color' => '#123456']);
        $player = User::factory()->player()->create();
        $profile = PlayerProfile::factory()->self($player)->create();
        TrainerPlayerAssociation::factory()->forTrainer($this->trainer)->forProfile($profile)->create();
        TrainerPlayerAssociation::factory()->forTrainer($other)->forProfile($profile)->create();

        $this->assertSame('#123456', $this->brandingProp($player, 'player.dashboard')['color']);

        $this->withSession([ContextResolver::SESSION_PLAYER => $profile->id, ContextResolver::SESSION_TRAINER => $other->id]);

        $this->assertSame('#ABCDEF', $this->brandingProp($player, 'player.dashboard')['color']);
    }

    public function test_admin_and_unconnected_users_get_default_branding(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $player = User::factory()->player()->create();

        $this->assertSame(['logo_url' => null, 'color' => null], $this->brandingProp($admin, 'admin.dashboard'));
        $this->assertSame(['logo_url' => null, 'color' => null], $this->brandingProp($player, 'player.dashboard'));
    }

    public function test_trainers_are_isolated_from_each_other(): void
    {
        $other = $this->makeTrainer('#ABCDEF');
        $this->save(['brand_color' => '#123456']);

        $this->assertSame('#ABCDEF', $this->brandingProp($other, 'trainer.dashboard')['color']);
        $this->assertSame('#ABCDEF', $other->trainerProfile->fresh()->brand_color);
    }
}
