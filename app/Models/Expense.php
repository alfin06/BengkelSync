<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money spent by the branch, paid from one of its cash accounts. Written only by
 * ExpenseService; never deleted (a wrong one is voided).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property Carbon $expense_date
 * @property int $category_id
 * @property string $description
 * @property int $amount
 * @property int $cash_account_id
 * @property string|null $attachment_path
 * @property int|null $created_by
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property int|null $voided_by
 * @property-read ExpenseCategory $category
 * @property-read CashAccount $account
 * @property-read User|null $creator
 */
class Expense extends Model
{
    use Auditable;
    use BelongsToBranch;
    use BelongsToTenant;

    protected $fillable = [
        'expense_date',
        'category_id',
        'description',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'attachment_path' => null,
        'created_by' => null,
        'voided_at' => null,
        'void_reason' => null,
        'voided_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
