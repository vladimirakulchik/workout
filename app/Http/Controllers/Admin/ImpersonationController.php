<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Lab404\Impersonate\Services\ImpersonateManager;

class ImpersonationController extends Controller
{
    public function take(Request $request, User $user, ImpersonateManager $impersonation): RedirectResponse
    {
        Gate::authorize('impersonate', $user);

        abort_if($impersonation->isImpersonating(), 403);
        abort_unless($request->user()->impersonate($user), 500);

        return redirect($impersonation->getTakeRedirectTo());
    }

    public function leave(ImpersonateManager $impersonation): RedirectResponse
    {
        if (! $impersonation->isImpersonating()) {
            return to_route('dashboard');
        }

        $impersonation->leave();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Impersonation ended.')]);

        return redirect($impersonation->getLeaveRedirectTo());
    }
}
