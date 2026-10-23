<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Counter behind DocumentNumber. Never read or increment it directly.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $type
 * @property int $year
 * @property int $last_number
 */
class DocumentSequence extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'type',
        'year',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
