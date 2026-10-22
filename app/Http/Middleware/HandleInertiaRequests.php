<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ContextResolver;
use App\Support\NavigationBuilder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
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
                'impersonating' => $user instanceof User && $user->isImpersonated()
                    ? ['name' => $user->name, 'role_label' => $user->role->label()]
                    : null,
            ],
            'navigation' => $user instanceof User ? (new NavigationBuilder)->for($user) : [],
            'context' => $user instanceof User && $user->isPlayer() ? $this->contextProps($user, $request) : null,
            'notifications' => $user instanceof User ? $this->notificationProps($user) : null,
            'branding' => null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{current: array<string, mixed>|null, options: array{yours: list<array<string, mixed>>, children: list<array<string, mixed>>}}
     */
    private function contextProps(User $user, Request $request): array
    {
        $resolver = app(ContextResolver::class);

        return [
            'current' => $resolver->resolve($user, $request->session()),
            'options' => $resolver->options($user),
        ];
    }

    /**
     * @return array{unread_count: int, recent: list<array{id: string, message: string, url: string|null, read: bool, created_at: string}>}
     */
    private function notificationProps(User $user): array
    {
        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'recent' => array_values($user->notifications()->limit(10)->get()->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'message' => (string) ($notification->data['message'] ?? ''),
                'url' => is_string($notification->data['url'] ?? null) ? $notification->data['url'] : null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at->toIso8601String(),
            ])->all()),
        ];
    }

    /**
     * @return array{id: int, first_name: string, last_name: string, name: string, email: string, role: string, photo_url: string, impersonating: bool}
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
            'photo_url' => $user->photoUrl(),
            'impersonating' => $user->isImpersonated(),
        ];
    }
}
