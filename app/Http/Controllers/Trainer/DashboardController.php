<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\CoachProfile;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('trainer/Dashboard', [
            'stats' => [
                ['label' => 'Coaches', 'value' => CoachProfile::query()->count()],
            ],
        ]);
    }
}
