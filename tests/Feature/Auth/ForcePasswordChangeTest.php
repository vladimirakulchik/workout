<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_who_must_change_password_are_redirected_from_app_routes(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.force'));
        $this->actingAs($user)->get(route('profile.edit'))->assertRedirect(route('password.force'));
    }

    public function test_force_page_is_rendered_for_users_who_must_change_password(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get(route('password.force'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/ForcePasswordChange'));
    }

    public function test_force_page_redirects_users_who_do_not_need_to_change_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('password.force'))->assertRedirect(route('dashboard'));
    }

    public function test_password_can_be_changed_and_flag_is_cleared(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->put(route('password.force.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_wrong_current_password_keeps_the_flag(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->from(route('password.force'))->put(route('password.force.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_new_password_must_differ_from_current(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->from(route('password.force'))->put(route('password.force.update'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_logout_is_still_available(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_guests_cannot_access_force_page(): void
    {
        $this->get(route('password.force'))->assertRedirect(route('login'));
    }
}
