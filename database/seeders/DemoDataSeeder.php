<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Admin\DeleteUser;
use App\Models\ChildPurchaseApproval;
use App\Models\CoachAvailabilityOverride;
use App\Models\CoachProfile;
use App\Models\ImpersonationLog;
use App\Models\PlayerProfile;
use App\Models\ShareLink;
use App\Models\TrainerPlayerAssociation;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Notifications\ChildPurchaseApprovalRequested;
use App\Notifications\ChildPurchaseApprovalResolved;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shared dataset builder: fills every application table with consistent data.
 *
 * Subclasses only choose the size. A login that owns a self profile always shares its
 * first and last name with that profile; only children without a login have their own name.
 */
abstract class DemoDataSeeder extends Seeder
{
    private const CHUNK = 1000;

    /**
     * Total number of demo users created (super admins, trainers, coaches and players).
     */
    abstract protected function userCount(): int;

    /**
     * Number of trainers; coaches, parents and approvals scale from it.
     */
    abstract protected function trainerCount(): int;

    protected function superAdminCount(): int
    {
        return 5;
    }

    protected function coachesPerTrainer(): int
    {
        return 3;
    }

    /**
     * Whether audit log rows are written for every created user (off for bulk data).
     */
    protected function logsUserCreation(): bool
    {
        return true;
    }

    protected function approvalCount(): int
    {
        return $this->trainerCount() * 2;
    }

    public function run(): void
    {
        $this->call(SuperAdminSeeder::class);

        if (! $this->logsUserCreation()) {
            activity()->disableLogging();
        }

        $admins = User::factory()->superAdmin()->count($this->superAdminCount())->create();
        $trainers = $this->createTrainers();
        $coaches = $this->createCoaches($trainers);
        [$parents, $players] = $this->createPlayers();

        $profiles = PlayerProfile::query()->get(['id', 'user_id', 'parent_user_id', 'is_child']);

        activity()->enableLogging();

        $links = $this->createShareLinks($trainers, $coaches);
        $this->associatePlayers($trainers, $profiles, $links);
        $this->createAvailabilities($profiles);
        $this->createCoachOverrides($trainers, $coaches);
        $this->createApprovals($trainers, $profiles);
        $this->createImpersonationHistory($admins, $trainers->concat($players)->concat($parents));
        $this->createDeletedUser($admins->first());
        $this->createAuditEntries($admins->first(), $trainers);
    }

    /**
     * @return Collection<int, User>
     */
    private function createTrainers(): Collection
    {
        $trainers = User::factory()->trainer()->count($this->trainerCount())->create();

        $this->insertInChunks('trainer_profiles', $trainers->map(fn (User $trainer): array => [
            ...TrainerProfile::factory()->make(['user_id' => $trainer->id])->getAttributes(),
            'created_at' => now(),
            'updated_at' => now(),
        ])->values()->all());

        return $trainers;
    }

    /**
     * @param  Collection<int, User>  $trainers
     * @return Collection<int, User>
     */
    private function createCoaches(Collection $trainers): Collection
    {
        $coaches = collect();

        foreach ($trainers as $trainer) {
            $users = User::factory()->coach()->count($this->coachesPerTrainer())->create();

            foreach ($users as $user) {
                CoachProfile::factory()->forTrainer($trainer)->public()->create(['user_id' => $user->id]);
            }

            $coaches = $coaches->concat($users);
        }

        return $coaches;
    }

    /**
     * Parents own a self profile plus one or two children; the rest are single players.
     * Also adds one unassigned coach so the "no trainer yet" state exists.
     *
     * @return array{0: Collection<int, User>, 1: Collection<int, User>}
     */
    private function createPlayers(): array
    {
        CoachProfile::factory()->create();

        $remaining = $this->userCount() - $this->superAdminCount() - $this->trainerCount()
            - $this->trainerCount() * $this->coachesPerTrainer() - 1 - 1;

        $parentCount = intdiv($remaining, 4);
        $soloCount = $remaining - $parentCount;

        $parents = User::factory()->player()->count($parentCount)->create();
        $solo = User::factory()->player()->count($soloCount)->create();

        $profiles = [];
        $now = now();

        foreach ($parents->concat($solo) as $user) {
            $profiles[] = $this->profileRow($user, null, $now);
        }

        foreach ($parents as $index => $parent) {
            for ($child = 0; $child < 1 + $index % 2; $child++) {
                $profiles[] = $this->profileRow(null, $parent, $now);
            }
        }

        $this->insertInChunks('player_profiles', $profiles);

        return [$parents, $solo];
    }

    /**
     * Raw profile row; a login's names are copied from the user, children get their own.
     *
     * @return array<string, mixed>
     */
    private function profileRow(?User $user, ?User $parent, mixed $now): array
    {
        $attributes = $user !== null
            ? PlayerProfile::factory()->make(['user_id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name])
            : PlayerProfile::factory()->child($parent)->make(['parent_user_id' => $parent->id]);

        return [
            ...$attributes->getAttributes(),
            'birth_date' => $attributes->birth_date->format('Y-m-d'),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * One reusable player link per trainer, plus coach invitations (accepted for each coach, one pending).
     *
     * @param  Collection<int, User>  $trainers
     * @param  Collection<int, User>  $coaches
     * @return array<int, int> Player share link id keyed by trainer id.
     */
    private function createShareLinks(Collection $trainers, Collection $coaches): array
    {
        $links = [];
        $coachTrainer = CoachProfile::query()->pluck('trainer_id', 'user_id');

        foreach ($trainers as $trainer) {
            $links[$trainer->id] = ShareLink::factory()->forTrainer($trainer)->create(['code' => ShareLink::generateCode()])->id;

            ShareLink::factory()->forTrainer($trainer)->coach()->create(['code' => ShareLink::generateCode(), 'name' => 'Pending coach']);
        }

        foreach ($coaches as $coach) {
            ShareLink::factory()->forTrainer($trainers->firstWhere('id', $coachTrainer[$coach->id]))->coach()->create([
                'code' => ShareLink::generateCode(),
                'target_email' => $coach->email,
                'name' => $coach->name,
                'uses_count' => 1,
                'is_active' => false,
            ]);
        }

        return $links;
    }

    /**
     * Every profile joins one trainer through that trainer's link; every fifth also joins a second one.
     *
     * @param  Collection<int, User>  $trainers
     * @param  Collection<int, PlayerProfile>  $profiles
     * @param  array<int, int>  $links
     */
    private function associatePlayers(Collection $trainers, Collection $profiles, array $links): void
    {
        $trainerIds = $trainers->pluck('id')->values();
        $rows = [];
        $uses = array_fill_keys($trainerIds->all(), 0);

        foreach ($profiles->values() as $index => $profile) {
            $targets = [$trainerIds[$index % $trainerIds->count()]];

            if ($index % 5 === 0 && $trainerIds->count() > 1) {
                $targets[] = $trainerIds[($index + 1) % $trainerIds->count()];
            }

            foreach ($targets as $trainerId) {
                $uses[$trainerId]++;
                $rows[] = [
                    'trainer_id' => $trainerId,
                    'player_profile_id' => $profile->id,
                    'share_link_id' => $links[$trainerId],
                    'connected_at' => now()->subDays($index % 90),
                    'status' => TrainerPlayerAssociation::STATUS_ACTIVE,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $this->insertInChunks('trainer_player_associations', $rows);

        foreach ($uses as $trainerId => $count) {
            ShareLink::query()->whereKey($links[$trainerId])->update(['uses_count' => $count]);
        }
    }

    /**
     * @param  Collection<int, PlayerProfile>  $profiles
     */
    private function createAvailabilities(Collection $profiles): void
    {
        $rows = [];

        foreach ($profiles as $profile) {
            $rows = [...$rows, ...$this->availabilityRows($profile->getMorphClass(), $profile->id, $profile->id % 7, 2)];
        }

        foreach (CoachProfile::query()->get(['id']) as $coachProfile) {
            $rows = [...$rows, ...$this->availabilityRows($coachProfile->getMorphClass(), $coachProfile->id, $coachProfile->id % 4, 3)];
        }

        $this->insertInChunks('availabilities', $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availabilityRows(string $type, int $ownerId, int $firstDay, int $days): array
    {
        $rows = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $rows[] = [
                'availabilityable_type' => $type,
                'availabilityable_id' => $ownerId,
                'day_of_week' => ($firstDay + $offset * 2) % 7,
                'start_time' => $offset % 2 === 0 ? '17:00:00' : '09:00:00',
                'end_time' => $offset % 2 === 0 ? '20:00:00' : '12:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, User>  $trainers
     * @param  Collection<int, User>  $coaches
     */
    private function createCoachOverrides(Collection $trainers, Collection $coaches): void
    {
        $coachTrainer = CoachProfile::query()->pluck('trainer_id', 'user_id');

        foreach ($coaches->filter(fn (User $coach): bool => $coach->id % 3 === 0) as $coach) {
            CoachAvailabilityOverride::factory()->create([
                'coach_id' => $coach->id,
                'overridden_by' => $coachTrainer[$coach->id],
            ]);
        }
    }

    /**
     * Pending, approved, denied and expired requests for children, with the matching in-app notifications.
     *
     * @param  Collection<int, User>  $trainers
     * @param  Collection<int, PlayerProfile>  $profiles
     */
    private function createApprovals(Collection $trainers, Collection $profiles): void
    {
        $children = $profiles->where('is_child', true)->values();
        $trainerIds = $trainers->pluck('id')->values();
        $states = ['pending', 'approved', 'denied', 'expired', 'tokens'];

        for ($index = 0; $index < min($this->approvalCount(), $children->count()); $index++) {
            $child = $children[$index];
            $trainer = $trainers->firstWhere('id', $trainerIds[$index % $trainerIds->count()]);
            $state = $states[$index % count($states)];

            $factory = ChildPurchaseApproval::factory()->forChild($child)->forTrainer($trainer);
            $factory = match ($state) {
                'approved' => $factory->approved(),
                'denied' => $factory->denied(),
                'expired' => $factory->expired(),
                'tokens' => $factory->tokens(),
                default => $factory,
            };
            $approval = $factory->create();

            $approval->load(['playerProfile', 'trainer']);
            $parent = User::query()->findOrFail($child->parent_user_id);

            $notification = in_array($state, ['approved', 'denied'], true)
                ? new ChildPurchaseApprovalResolved($approval)
                : new ChildPurchaseApprovalRequested($approval);

            $parent->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'data' => $notification->toArray($parent),
                'read_at' => $state === 'pending' ? null : now(),
            ]);
        }
    }

    /**
     * @param  Collection<int, User>  $admins
     * @param  Collection<int, User>  $targets
     */
    private function createImpersonationHistory(Collection $admins, Collection $targets): void
    {
        foreach ($targets->take(5) as $index => $target) {
            ImpersonationLog::factory()->create([
                'admin_id' => $admins[$index % $admins->count()]->id,
                'impersonated_id' => $target->id,
            ]);
        }
    }

    /**
     * Goes through the real delete action so the anonymized row, deletion log and audit entry are all genuine.
     */
    private function createDeletedUser(User $admin): void
    {
        $victim = User::factory()->player()->create();
        PlayerProfile::factory()->self($victim)->create();

        app(DeleteUser::class)->handle($admin, $victim, 'Seeded erasure request');
    }

    /**
     * @param  Collection<int, User>  $trainers
     */
    private function createAuditEntries(User $admin, Collection $trainers): void
    {
        foreach ($trainers->take(5) as $trainer) {
            activity()->performedOn($trainer)->causedBy($admin)->event('created')->log('Trainer created');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertInChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
