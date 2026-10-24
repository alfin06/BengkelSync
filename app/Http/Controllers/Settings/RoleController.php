<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\RoleRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roles and the permission matrix (SPEC section 2): the owner decides what each role may do.
 */
class RoleController extends Controller
{
    /** The usual actions get a column of the matrix; other actions are listed per row. */
    private const STANDARD_ACTIONS = ['view', 'create', 'update', 'delete'];

    public function __construct(private readonly RoleService $roles) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);

        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Settings/Roles', [
            'roles' => $this->roles->roles()->loadCount('users')->map(fn (Role $role) => [
                'id' => $role->id,
                'label' => RoleService::label($role),
                'is_system' => RoleName::tryFrom($role->name) !== null,
                'is_owner' => $role->isOwnerRole(),
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions->count(),
                'can' => ['update' => $actor->can('update', $role), 'delete' => $actor->can('delete', $role)],
            ])->all(),
            'can' => ['create' => $actor->can('create', Role::class)],
        ]);
    }

    /**
     * The matrix of one role. ?copy=ID starts a new role from an existing one.
     */
    public function edit(Request $request, Role $role): Response
    {
        Gate::authorize('view', $role);

        return Inertia::render('Settings/RoleMatrix', [
            'role' => [
                'id' => $role->id,
                'label' => RoleService::label($role),
                'name' => $role->name,
                'is_system' => RoleName::tryFrom($role->name) !== null,
                'is_owner' => $role->isOwnerRole(),
                'permissions' => $role->permissions()->pluck('name')->all(),
            ],
            'matrix' => $this->matrix(),
            'can' => ['update' => $request->user()?->can('update', $role) ?? false],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Role::class);

        $copy = $request->filled('copy')
            ? $this->roles->roles()->firstWhere('id', $request->integer('copy'))
            : null;

        return Inertia::render('Settings/RoleMatrix', [
            'role' => [
                'id' => null,
                'label' => '',
                'name' => '',
                'is_system' => false,
                'is_owner' => false,
                'permissions' => $copy?->permissions->pluck('name')->all() ?? [],
            ],
            'matrix' => $this->matrix(),
            'can' => ['update' => true],
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = $this->roles->create($request->string('name')->toString(), $request->permissions());

        return redirect()->route('settings.roles.edit', $role)->with('success', __('roles_admin.saved', ['name' => RoleService::label($role)]));
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->update($role, $request->filled('name') ? $request->string('name')->toString() : null, $request->permissions());

        return back()->with('success', __('roles_admin.saved', ['name' => RoleService::label($role)]));
    }

    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $this->roles->delete($role);

        return redirect()->route('settings.roles.index')->with('success', __('roles_admin.deleted'));
    }

    /**
     * One row per resource, in the order of the Permission enum (SPEC section 2):
     * the usual actions, and any other actions of that resource. Billing stays with the Owner.
     *
     * @return list<array{resource: string, standard: array<string, string|null>, extra: list<string>}>
     */
    private function matrix(): array
    {
        $rows = [];

        foreach (Permission::cases() as $permission) {
            if ($permission === Permission::BillingManage) {
                continue;
            }

            $resource = $permission->resource();
            $action = explode('.', $permission->value, 2)[1];
            $rows[$resource] ??= ['resource' => $resource, 'standard' => array_fill_keys(self::STANDARD_ACTIONS, null), 'extra' => []];

            if (in_array($action, self::STANDARD_ACTIONS, true)) {
                $rows[$resource]['standard'][$action] = $permission->value;
            } else {
                $rows[$resource]['extra'][] = $permission->value;
            }
        }

        return array_values($rows);
    }
}
