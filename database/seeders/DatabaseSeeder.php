<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ChildPurchaseApproval;
use App\Models\CoachProfile;
use App\Models\PlayerProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedSuperAdmin();

        if (app()->environment('local')) {
            $this->seedDemoUsers();
        }
    }

    /**
     * Create the verified Super Admin from configuration; skipped when credentials are not set.
     */
    private function seedSuperAdmin(): void
    {
        $email = config('app.super_admin_email');
        $password = config('app.super_admin_password');

        if (! $email || ! $password) {
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => $password,
                'role' => UserRole::SuperAdmin,
                'email_verified_at' => now(),
            ],
        );
    }

    private function seedDemoUsers(): void
    {
        User::factory()->superAdmin()->count(5)->create();

        $trainers = TrainerProfile::factory()->count(2)->create()->map->user;

        foreach ($trainers as $index => $trainer) {
            CoachProfile::factory()->forTrainer($trainer)->count($index === 0 ? 2 : 1)->create();
        }

        PlayerProfile::factory()->count(4)->create();

        $parent = User::factory()->player()->create();
        PlayerProfile::factory()->self($parent)->create();
        $children = PlayerProfile::factory()->child($parent)->count(2)->create();

        ChildPurchaseApproval::factory()->forChild($children->first())->forTrainer($trainers->first())->create();
    }
}
