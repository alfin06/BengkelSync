<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $locale
 * @property bool $is_active
 * @property bool $all_branches
 * @property Carbon|null $last_login_at
 * @property-read Tenant $tenant
 */
class User extends Authenticatable
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'password',
        'locale',
        'is_active',
        'all_branches',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'phone' => null,
        'locale' => null,
        'is_active' => true,
        'all_branches' => false,
        'last_login_at' => null,
        'remember_token' => null,
    ];

    /** @var list<string> */
    protected array $auditExclude = ['last_login_at'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'all_branches' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Users are looked up by email before any tenant is known (login, password reset),
     * so querying users without a tenant context is allowed. Inside a request the
     * tenant scope still applies.
     */
    public static function requiresTenantContext(): bool
    {
        return false;
    }

    /**
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * The branches this user may work in (SPEC section 2, "Branch access").
     *
     * @return Builder<Branch>
     */
    public function accessibleBranches(): Builder
    {
        if ($this->all_branches) {
            return Branch::query()->where('tenant_id', $this->tenant_id);
        }

        return Branch::query()
            ->where('tenant_id', $this->tenant_id)
            ->whereHas('users', fn (Builder $query) => $query->whereKey($this->getKey()));
    }

    public function canAccessBranch(Branch $branch): bool
    {
        return $this->accessibleBranches()->whereKey($branch->getKey())->exists();
    }

    /**
     * Soft-deleted users never get here: the auth provider does not load them, so they
     * are logged out on their next request automatically.
     */
    public function canLogIn(): bool
    {
        return $this->is_active && $this->tenant->isActive();
    }

    public function isOwner(): bool
    {
        return $this->hasRole(RoleName::Owner->value);
    }
}
