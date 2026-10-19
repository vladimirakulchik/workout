<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $counts = User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                ['label' => 'Total users', 'value' => (int) $counts->sum()],
                ['label' => 'Trainers', 'value' => (int) ($counts[UserRole::Trainer->value] ?? 0)],
                ['label' => 'Coaches', 'value' => (int) ($counts[UserRole::Coach->value] ?? 0)],
                ['label' => 'Players', 'value' => (int) ($counts[UserRole::Player->value] ?? 0)],
            ],
        ]);
    }
}
