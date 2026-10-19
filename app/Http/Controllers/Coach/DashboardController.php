<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $profile = $request->user()->coachProfile;

        return Inertia::render('coach/Dashboard', [
            'stats' => [
                ['label' => 'Public profile', 'value' => $profile?->is_public ? 'Visible' : 'Hidden'],
                ['label' => 'Joined', 'value' => $profile?->joined_at?->toFormattedDateString() ?? '—'],
            ],
        ]);
    }
}
