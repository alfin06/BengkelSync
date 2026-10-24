<?php

namespace App\Models;

use App\Enums\Permission;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property bool $is_restricted only users with expense.view_restricted see its expenses
 * @property bool $is_active
 * @property int $sort
 */
class ExpenseCategory extends Model
{
    use Auditable;
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'is_restricted',
        'is_active',
        'sort',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_restricted' => false,
        'is_active' => true,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_restricted' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * Categories a user may see: restricted ones only with expense.view_restricted.
     *
     * @param  Builder<ExpenseCategory>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->when(! $user->can(Permission::ExpenseViewRestricted->value), fn (Builder $query) => $query->where('is_restricted', false));
    }
}
