<?php

namespace App\Models;

use App\Enums\CashAccountType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CashAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place money is kept in a branch: the cash drawer, petty cash, a bank account, …
 * Its balance comes from its movements (CashService::balance()).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $name
 * @property CashAccountType $type
 * @property bool $is_active
 * @property int $sort
 */
class CashAccount extends Model
{
    use Auditable;
    use BelongsToBranch;
    use BelongsToTenant;

    /** @use HasFactory<CashAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'is_active',
        'sort',
    ];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => CashAccountType::class,
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
