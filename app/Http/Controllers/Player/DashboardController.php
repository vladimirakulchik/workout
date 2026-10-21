<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            'wallet' => [
                'read_only' => true,
                'balance' => null,
                'can_purchase' => Gate::forUser($user)->denies('child.restricted'),
            ],
        ]);
    }
}
