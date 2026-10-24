<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ResetUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\RoleService;
use App\Services\Users\UserService;
use App\Support\BranchTime;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff accounts of the shop (SPEC section 9: the owner creates users with a password).
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly RoleService $roles,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        /** @var User $actor */
        $actor = $request->user();
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $term = trim($filters['search'] ?? '');

        $users = User::query()
            ->with(['roles', 'branches'])
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $where) => $where
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (User $user) => $this->present($actor, $user));

        return Inertia::render('Settings/Users', [
            'users' => $users,
            'filters' => ['search' => $term],
            // Only what this person may hand out: roles and branches they hold themselves.
            'roles' => $this->roles->assignableRoles($actor)->map(fn (Role $role) => ['value' => $role->id, 'label' => RoleService::label($role)])->all(),
            'branches' => $actor->accessibleBranches()->orderBy('name')->get(['id', 'name'])->map(fn (Branch $branch) => ['id' => $branch->id, 'name' => $branch->name])->all(),
            'can' => [
                'create' => $actor->can('create', User::class),
                'allBranches' => $actor->all_branches || $actor->isOwner(),
                'roles' => $actor->can('viewAny', Role::class),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        /** @var Role $role */
        $role = $request->role();
        $user = $this->users->create(
            [...$request->details(), 'password' => $request->string('password')->toString(), 'all_branches' => $request->boolean('all_branches')],
            $role->name,
            $request->boolean('all_branches') ? [] : $request->branchIds(),
        );

        return back()->with('success', __('users_admin.created', ['name' => $user->name]));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        /** @var Role $role */
        $role = $request->role();
        $this->users->update($user, $request->details(), $role->name, $request->branchIds(), $request->boolean('all_branches'), $request->boolean('is_active'));

        return back()->with('success', __('users_admin.saved', ['name' => $user->name]));
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->users->resetPassword($user, $request->string('password')->toString());

        return back()->with('success', __('users_admin.password_reset', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $this->users->delete($user);

        return back()->with('success', __('users_admin.deleted', ['name' => $user->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $actor, User $user): array
    {
        /** @var Role|null $role */
        $role = $user->roles->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone === null ? null : Phone::format($user->phone),
            'locale' => $user->locale,
            'role_id' => $role?->id,
            'role_label' => $role === null ? null : RoleService::label($role),
            'all_branches' => $user->all_branches,
            'branch_ids' => $user->branches->pluck('id')->all(),
            'branch_names' => $user->branches->pluck('name')->all(),
            'is_active' => $user->is_active,
            'is_self' => $actor->is($user),
            'last_login_label' => BranchTime::label($user->last_login_at),
            'can' => [
                'update' => $actor->can('update', $user),
                'delete' => $actor->can('delete', $user),
            ],
        ];
    }
}
