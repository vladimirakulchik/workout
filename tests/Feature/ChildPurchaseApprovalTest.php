<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Player\RequestChildPurchaseApproval;
use App\Enums\ApprovalStatus;
use App\Enums\PaymentType;
use App\Events\ChildPurchaseApproved;
use App\Events\ChildPurchaseDenied;
use App\Models\ChildPurchaseApproval;
use App\Models\PlayerProfile;
use App\Models\TrainerPlayerAssociation;
use App\Models\User;
use App\Notifications\ChildPurchaseApprovalRequested;
use App\Notifications\ChildPurchaseApprovalResolved;
use App\Notifications\ChildPurchaseMoreInfoRequested;
use App\Notifications\ChildTokenPurchaseMade;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ChildPurchaseApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    private User $childLogin;

    private User $trainer;

    private PlayerProfile $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = User::factory()->player()->create();
        $this->childLogin = User::factory()->player()->create();
        $this->trainer = User::factory()->trainer()->create();
        $this->child = PlayerProfile::factory()->child($this->parent)->create(['user_id' => $this->childLogin->id]);

        TrainerPlayerAssociation::factory()->forTrainer($this->trainer)->forProfile($this->child)->create();
    }

    private function pending(array $attributes = []): ChildPurchaseApproval
    {
        return ChildPurchaseApproval::factory()
            ->forChild($this->child)
            ->forTrainer($this->trainer)
            ->create($attributes);
    }

    private function request(PaymentType $type): ?ChildPurchaseApproval
    {
        return app(RequestChildPurchaseApproval::class)->handle($this->child, $this->trainer, 'Camp week', 5000, 'usd', $type);
    }

    public function test_usd_purchase_is_always_pending_even_when_token_spending_is_allowed(): void
    {
        Notification::fake();
        $this->child->update(['allow_token_spending' => true]);

        $approval = $this->request(PaymentType::Usd);

        $this->assertSame(ApprovalStatus::Pending, $approval->status);
        $this->assertSame('USD', $approval->currency);
        $this->assertSame($this->parent->id, $approval->parent_user_id);
        $this->assertSame($this->trainer->id, $approval->trainer_id);
        $this->assertEqualsWithDelta(now()->addHours(48)->timestamp, $approval->expires_at->timestamp, 5);
        Notification::assertSentTo($this->parent, ChildPurchaseApprovalRequested::class, fn ($notification, array $channels) => $channels === ['mail', 'database']);
    }

    public function test_tokens_need_approval_when_spending_is_not_allowed(): void
    {
        Notification::fake();

        $approval = $this->request(PaymentType::Tokens);

        $this->assertNotNull($approval);
        Notification::assertSentTo($this->parent, ChildPurchaseApprovalRequested::class);
        Notification::assertNotSentTo($this->parent, ChildTokenPurchaseMade::class);
    }

    public function test_tokens_skip_approval_when_spending_is_allowed_and_parent_is_informed(): void
    {
        Notification::fake();
        $this->child->update(['allow_token_spending' => true]);

        $this->assertNull($this->request(PaymentType::Tokens));

        $this->assertSame(0, ChildPurchaseApproval::query()->count());
        Notification::assertSentTo($this->parent, ChildTokenPurchaseMade::class);
        Notification::assertNotSentTo($this->parent, ChildPurchaseApprovalRequested::class);
    }

    public function test_purchase_request_stub_uses_the_current_context(): void
    {
        Notification::fake();

        $this->actingAs($this->childLogin)
            ->post(route('player.purchases.request'), [
                'description' => 'Camp week',
                'amount_cents' => 5000,
                'currency' => 'usd',
                'payment_type' => 'usd',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $approval = ChildPurchaseApproval::query()->sole();
        $this->assertSame($this->child->id, $approval->player_profile_id);
        $this->assertSame($this->trainer->id, $approval->trainer_id);
        Notification::assertSentTo($this->parent, ChildPurchaseApprovalRequested::class);
    }

    public function test_purchase_request_validates_input(): void
    {
        $this->actingAs($this->childLogin)
            ->post(route('player.purchases.request'), ['payment_type' => 'gold', 'amount_cents' => 0])
            ->assertSessionHasErrors(['description', 'amount_cents', 'currency', 'payment_type']);

        $this->assertSame(0, ChildPurchaseApproval::query()->count());
    }

    public function test_adult_profiles_cannot_request_approvals(): void
    {
        $adult = User::factory()->player()->create();
        $profile = PlayerProfile::factory()->self($adult)->create();
        TrainerPlayerAssociation::factory()->forTrainer($this->trainer)->forProfile($profile)->create();

        $this->actingAs($adult)
            ->post(route('player.purchases.request'), [
                'description' => 'Camp week',
                'amount_cents' => 5000,
                'currency' => 'usd',
                'payment_type' => 'usd',
            ])
            ->assertSessionHasErrors('player_profile_id');

        $this->assertSame(0, ChildPurchaseApproval::query()->count());
    }

    public function test_parent_approves_with_notes(): void
    {
        Event::fake([ChildPurchaseApproved::class, ChildPurchaseDenied::class]);
        Notification::fake();
        $approval = $this->pending();

        $this->actingAs($this->parent)
            ->post(route('player.approvals.approve', $approval), ['notes' => 'Have fun'])
            ->assertRedirect(route('player.approvals.index'))
            ->assertSessionHasNoErrors();

        $approval->refresh();
        $this->assertSame(ApprovalStatus::Approved, $approval->status);
        $this->assertSame('Have fun', $approval->parent_notes);
        $this->assertNotNull($approval->responded_at);
        Event::assertDispatched(ChildPurchaseApproved::class, fn ($event) => $event->approval->is($approval));
        Event::assertNotDispatched(ChildPurchaseDenied::class);
        Notification::assertSentTo($this->childLogin, ChildPurchaseApprovalResolved::class);
    }

    public function test_parent_denies_with_notes(): void
    {
        Event::fake([ChildPurchaseApproved::class, ChildPurchaseDenied::class]);
        Notification::fake();
        $approval = $this->pending();

        $this->actingAs($this->parent)
            ->post(route('player.approvals.deny', $approval), ['notes' => 'Too expensive'])
            ->assertRedirect(route('player.approvals.index'));

        $approval->refresh();
        $this->assertSame(ApprovalStatus::Denied, $approval->status);
        $this->assertSame('Too expensive', $approval->parent_notes);
        Event::assertDispatched(ChildPurchaseDenied::class);
        Event::assertNotDispatched(ChildPurchaseApproved::class);
        Notification::assertSentTo($this->childLogin, ChildPurchaseApprovalResolved::class);
    }

    public function test_only_the_owning_parent_can_decide(): void
    {
        $approval = $this->pending();
        $otherParent = User::factory()->player()->create();
        $trainer = $this->trainer;

        foreach (['approve', 'deny'] as $action) {
            $this->actingAs($this->childLogin)->post(route("player.approvals.$action", $approval))->assertForbidden();
            $this->actingAs($otherParent)->post(route("player.approvals.$action", $approval))->assertForbidden();
            $this->actingAs($trainer)->post(route("player.approvals.$action", $approval))->assertForbidden();
        }

        $this->actingAs($otherParent)->post(route('player.approvals.request-info', $approval), ['note' => 'Why?'])->assertForbidden();

        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
    }

    public function test_a_request_cannot_be_decided_twice(): void
    {
        Event::fake([ChildPurchaseApproved::class, ChildPurchaseDenied::class]);
        $approval = $this->pending();

        $this->actingAs($this->parent)->post(route('player.approvals.approve', $approval));
        $this->actingAs($this->parent)
            ->post(route('player.approvals.deny', $approval), ['notes' => 'changed my mind'])
            ->assertSessionHasErrors('approval');

        $this->assertSame(ApprovalStatus::Approved, $approval->fresh()->status);
        Event::assertNotDispatched(ChildPurchaseDenied::class);
        Event::assertDispatchedTimes(ChildPurchaseApproved::class, 1);
    }

    public function test_an_overdue_request_cannot_be_decided(): void
    {
        $approval = $this->pending(['expires_at' => now()->subMinute()]);

        $this->actingAs($this->parent)
            ->post(route('player.approvals.approve', $approval))
            ->assertSessionHasErrors('approval');

        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
    }

    public function test_request_more_info_stores_note_and_notifies_the_child(): void
    {
        Notification::fake();
        $approval = $this->pending();

        $this->actingAs($this->parent)
            ->post(route('player.approvals.request-info', $approval), ['note' => 'What is this for?'])
            ->assertRedirect(route('player.approvals.index'));

        $approval->refresh();
        $this->assertSame(ApprovalStatus::Pending, $approval->status);
        $this->assertSame('What is this for?', $approval->parent_notes);
        Notification::assertSentTo($this->childLogin, ChildPurchaseMoreInfoRequested::class);

        $this->actingAs($this->parent)
            ->post(route('player.approvals.request-info', $approval), ['note' => ''])
            ->assertSessionHasErrors('note');
    }

    public function test_expire_command_denies_overdue_requests_and_notifies_child_and_parent(): void
    {
        Event::fake([ChildPurchaseDenied::class]);
        Notification::fake();
        $overdue = $this->pending(['expires_at' => now()->subMinute()]);
        $fresh = $this->pending();
        $answered = $this->pending(['expires_at' => now()->subMinute(), 'status' => ApprovalStatus::Approved]);

        $this->artisan('approvals:expire')->assertSuccessful();

        $this->assertSame(ApprovalStatus::Expired, $overdue->fresh()->status);
        $this->assertNotNull($overdue->fresh()->responded_at);
        $this->assertSame(ApprovalStatus::Pending, $fresh->fresh()->status);
        $this->assertSame(ApprovalStatus::Approved, $answered->fresh()->status);
        Notification::assertSentTo($this->childLogin, ChildPurchaseApprovalResolved::class);
        Notification::assertSentTo($this->parent, ChildPurchaseApprovalResolved::class);
        Event::assertDispatchedTimes(ChildPurchaseDenied::class, 1);

        $this->artisan('approvals:expire')->assertSuccessful();
        Event::assertDispatchedTimes(ChildPurchaseDenied::class, 1);
    }

    public function test_expire_command_is_scheduled_every_five_minutes_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'approvals:expire'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_parent_sees_pending_and_history_with_decision_rights(): void
    {
        $pending = $this->pending();
        $done = $this->pending(['status' => ApprovalStatus::Denied]);
        ChildPurchaseApproval::factory()->create();

        $this->actingAs($this->parent)
            ->get(route('player.approvals.index'))
            ->assertInertia(fn ($page) => $page
                ->component('player/Approvals/Index')
                ->where('readOnly', false)
                ->has('pending', 1)
                ->where('pending.0.id', $pending->id)
                ->where('pending.0.can_respond', true)
                ->has('history', 1)
                ->where('history.0.id', $done->id)
                ->where('history.0.can_respond', false));
    }

    public function test_child_sees_own_requests_read_only(): void
    {
        $mine = $this->pending();
        $siblingProfile = PlayerProfile::factory()->child($this->parent)->create();
        ChildPurchaseApproval::factory()->forChild($siblingProfile)->forTrainer($this->trainer)->create();

        $this->actingAs($this->childLogin)
            ->get(route('player.approvals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('readOnly', true)
                ->has('pending', 1)
                ->where('pending.0.id', $mine->id)
                ->where('pending.0.can_respond', false));
    }

    public function test_non_players_cannot_open_the_approvals_page(): void
    {
        $this->actingAs($this->trainer)->get(route('player.approvals.index'))->assertForbidden();
    }
}
