<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\TrainerWelcomeMail;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateTrainer
{
    /**
     * Create a trainer with a temporary password, profile and audit entry, then queue the welcome mail.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(User $admin, array $data): User
    {
        $temporaryPassword = Str::password(16, symbols: false);

        $trainer = DB::transaction(function () use ($admin, $data, $temporaryPassword): User {
            $trainer = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $temporaryPassword,
                'role' => UserRole::Trainer,
                'status' => UserStatus::Active,
                'must_change_password' => true,
            ]);

            TrainerProfile::create([
                'user_id' => $trainer->id,
                'business_name' => $data['business_name'],
            ]);

            activity()
                ->performedOn($trainer)
                ->causedBy($admin)
                ->event('trainer_created')
                ->withProperties([
                    'business_name' => $data['business_name'],
                    'email' => $trainer->email,
                    'created_by' => $admin->email,
                ])
                ->log('Trainer created');

            return $trainer;
        });

        Mail::to($trainer)->send(new TrainerWelcomeMail($trainer, $temporaryPassword));

        return $trainer;
    }
}
