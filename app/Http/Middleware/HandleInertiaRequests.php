<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\NavigationBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user instanceof User ? $this->userProps($user, $request) : null,
            ],
            'navigation' => $user instanceof User ? (new NavigationBuilder)->for($user) : [],
            'branding' => null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{id: int, first_name: string, last_name: string, name: string, email: string, role: string, photo_url: string|null, impersonating: bool}
     */
    private function userProps(User $user, Request $request): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'photo_url' => $user->photo_path ? Storage::url($user->photo_path) : null,
            'impersonating' => $request->session()->has('impersonator_id'),
        ];
    }
}
