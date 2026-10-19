<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('player/Dashboard', [
            'stats' => [
                ['label' => 'Children', 'value' => $user->childProfiles()->count()],
                ['label' => 'Player profiles', 'value' => $user->playerProfiles()->count()],
            ],
        ]);
    }
}
