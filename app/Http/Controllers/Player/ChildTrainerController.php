<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Actions\Player\AddChildToTrainer;
use App\Actions\Player\RemoveChildFromTrainer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Player\AddChildTrainerRequest;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ChildTrainerController extends Controller
{
    public function store(AddChildTrainerRequest $request, PlayerProfile $childProfile, AddChildToTrainer $addChildToTrainer): RedirectResponse
    {
        Gate::authorize('update', $childProfile);

        $trainer = $request->filled('code')
            ? $addChildToTrainer->viaLink($childProfile, (string) $request->input('code'))
            : $addChildToTrainer->viaMyTrainer($request->user(), $childProfile, (int) $request->input('trainer_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':child added to :trainer.', [
            'child' => $childProfile->first_name,
            'trainer' => $trainer->displayName(),
        ])]);

        return redirect()->route('player.family.index');
    }

    public function destroy(PlayerProfile $childProfile, User $trainer, RemoveChildFromTrainer $removeChildFromTrainer): RedirectResponse
    {
        Gate::authorize('update', $childProfile);

        $removeChildFromTrainer->handle($childProfile, $trainer);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':child removed from :trainer.', [
            'child' => $childProfile->first_name,
            'trainer' => $trainer->displayName(),
        ])]);

        return redirect()->route('player.family.index');
    }
}
