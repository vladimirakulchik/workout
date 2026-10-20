<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateTrainer;
use App\Actions\Admin\DeactivateUser;
use App\Actions\Admin\DeleteUser;
use App\Actions\Admin\ReactivateUser;
use App\Actions\Profile\UpdateUserProfile;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteUserRequest;
use App\Http\Requests\Admin\IndexUsersRequest;
use App\Http\Requests\Admin\StoreTrainerRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const PER_PAGE = 25;

    public function index(IndexUsersRequest $request): Response
    {
        $filters = $request->safe()->only(['search', 'role', 'status', 'sort', 'direction']);
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $users = User::query()
            ->select(['id', 'first_name', 'last_name', 'email', 'role', 'status', 'phone', 'created_at'])
            ->when($filters['search'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.addcslashes(trim($term), '\\%_').'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(
                $sort === 'name',
                fn (Builder $query) => $query->orderBy('first_name', $direction)->orderBy('last_name', $direction),
                fn (Builder $query) => $query->orderBy($sort, $direction),
            )
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => $this->listRow($user, $request->user()));

        return Inertia::render('admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'roles' => array_map(
                fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                UserRole::cases(),
            ),
            'statuses' => array_map(
                fn (UserStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                UserStatus::cases(),
            ),
        ]);
    }

    public function store(StoreTrainerRequest $request, CreateTrainer $createTrainer): RedirectResponse
    {
        $createTrainer->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trainer created. A welcome email has been sent.')]);

        return to_route('admin.users.index');
    }

    public function edit(User $user, UpdateUserProfile $updateProfile): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/Users/Edit', [
            'managedUser' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'status' => $user->status->value,
                'created_at' => $user->created_at?->toDateString(),
                'photo_url' => $user->photoUrl(),
            ],
            'roleProfile' => $updateProfile->roleProfileProps($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserProfile $updateProfile): RedirectResponse
    {
        $updateProfile->handle($user, $request->validated(), $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.edit', $user);
    }

    public function deactivate(Request $request, User $user, DeactivateUser $deactivateUser): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $deactivateUser->handle($request->user(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deactivated.')]);

        return back();
    }

    public function reactivate(Request $request, User $user, ReactivateUser $reactivateUser): RedirectResponse
    {
        Gate::authorize('reactivate', $user);

        $reactivateUser->handle($request->user(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User reactivated.')]);

        return back();
    }

    public function destroy(DeleteUserRequest $request, User $user, DeleteUser $deleteUser): RedirectResponse
    {
        $deleteUser->handle($request->user(), $user, $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted and anonymized.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(User $user, User $viewer): array
    {
        return [
            'id' => $user->id,
            'name' => $user->displayName(),
            'can_deactivate' => $viewer->can('deactivate', $user),
            'can_reactivate' => $viewer->can('reactivate', $user),
            'can_delete' => $viewer->can('delete', $user),
            'can_impersonate' => $viewer->can('impersonate', $user),
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'status' => $user->status->value,
            'status_label' => $user->status->label(),
            'created_at' => $user->created_at?->toDateString(),
        ];
    }
}
