<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The previous version of an invoice, saved by InvoiceService::revise() (SPEC section 6).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $invoice_id
 * @property int $revision_no
 * @property array<string, mixed> $snapshot
 * @property string $reason
 * @property int|null $edited_by
 * @property Carbon|null $created_at
 */
class InvoiceRevision extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id',
        'revision_no',
        'snapshot',
        'reason',
        'edited_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'edited_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'revision_no' => 'integer',
        ];
    }
}
