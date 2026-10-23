<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A minimal branch-scoped model, used to test BelongsToBranch until the first real
 * operational model (work orders) exists.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $body
 */
class BranchNote extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'branch_id', 'body'];
}
