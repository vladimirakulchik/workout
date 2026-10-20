<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trainer;

use App\Actions\Trainer\CreateShareLink;
use App\Enums\ShareLinkType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\StoreShareLinkRequest;
use App\Models\ShareLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShareLinkController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ShareLink::class);

        $playerLink = ShareLink::query()
            ->where('type', ShareLinkType::Player)
            ->where('is_active', true)
            ->first();

        $coachLinks = ShareLink::query()
            ->where('type', ShareLinkType::Coach)
            ->latest('id')
            ->limit(50)
            ->get();

        return Inertia::render('trainer/ShareLinks/Index', [
            'playerLink' => $playerLink === null ? null : $this->row($playerLink),
            'coachLinks' => $coachLinks->map(fn (ShareLink $link): array => $this->row($link))->all(),
        ]);
    }

    public function store(StoreShareLinkRequest $request, CreateShareLink $createShareLink): RedirectResponse
    {
        $createShareLink->handle($request->user(), ShareLinkType::Coach, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coach invitation link created.')]);

        return back();
    }

    public function regenerate(Request $request, CreateShareLink $createShareLink): RedirectResponse
    {
        Gate::authorize('create', ShareLink::class);

        $createShareLink->regeneratePlayerLink($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player link regenerated. The old link no longer works.')]);

        return back();
    }

    public function deactivate(ShareLink $shareLink): RedirectResponse
    {
        Gate::authorize('deactivate', $shareLink);

        $shareLink->forceFill(['is_active' => false])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link deactivated.')]);

        return back();
    }

    /**
     * @return array{id: int, url: string, type: string, name: string|null, target_email: string|null, message: string|null, expires_at: string|null, uses_count: int, max_uses: int|null, status: string, can_deactivate: bool}
     */
    private function row(ShareLink $link): array
    {
        return [
            'id' => $link->id,
            'url' => $link->url(),
            'type' => $link->type->value,
            'name' => $link->name,
            'target_email' => $link->target_email,
            'message' => $link->message,
            'expires_at' => $link->expires_at?->toIso8601String(),
            'uses_count' => $link->uses_count,
            'max_uses' => $link->max_uses,
            'status' => $link->status(),
            'can_deactivate' => $link->is_active,
        ];
    }
}
