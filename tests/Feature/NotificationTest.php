<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ChildPurchaseApproval;
use App\Models\User;
use App\Notifications\ChildPurchaseApprovalRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function notifyWithApproval(User $parent): string
    {
        $approval = ChildPurchaseApproval::factory()->create(['parent_user_id' => $parent->id]);
        $approval->load(['playerProfile', 'trainer']);
        $parent->notify(new ChildPurchaseApprovalRequested($approval));

        return $parent->notifications()->firstOrFail()->id;
    }

    public function test_unread_count_and_recent_items_are_shared_with_every_page(): void
    {
        $parent = User::factory()->player()->create();
        $this->notifyWithApproval($parent);

        $this->actingAs($parent)
            ->get(route('player.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('notifications.unread_count', 1)
                ->where('notifications.recent.0.read', false)
                ->where('notifications.recent.0.url', route('player.approvals.index', absolute: false)));
    }

    public function test_mark_read_marks_the_notification_and_opens_its_page(): void
    {
        $parent = User::factory()->player()->create();
        $id = $this->notifyWithApproval($parent);

        $this->actingAs($parent)
            ->post(route('notifications.read', $id))
            ->assertRedirect(route('player.approvals.index', absolute: false));

        $this->assertSame(0, $parent->unreadNotifications()->count());
    }

    public function test_a_user_cannot_mark_someone_elses_notification(): void
    {
        $parent = User::factory()->player()->create();
        $id = $this->notifyWithApproval($parent);

        $this->actingAs(User::factory()->player()->create())
            ->post(route('notifications.read', $id))
            ->assertNotFound();

        $this->assertSame(1, $parent->unreadNotifications()->count());
    }

    public function test_mark_all_read(): void
    {
        $parent = User::factory()->player()->create();
        $this->notifyWithApproval($parent);
        $this->notifyWithApproval($parent);

        $this->actingAs($parent)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $parent->unreadNotifications()->count());
    }

    public function test_guests_cannot_mark_notifications(): void
    {
        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    }
}
