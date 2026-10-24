<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MechanicFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A mechanic of one branch. No login: staff assign mechanics to work orders.
 *
 * The NIK is encrypted at rest and only shown in full to users with
 * mechanic.view_identity; the KTP photo lives on the private disk.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $name
 * @property string|null $phone E.164
 * @property string|null $nik 16 digits, decrypted
 * @property string|null $ktp_path path on the private disk
 * @property bool $is_active
 * @property string|null $notes
 */
class Mechanic extends Model
{
    use Auditable;
    use BelongsToBranch;
    use BelongsToTenant;

    /** @use HasFactory<MechanicFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'nik',
        'is_active',
        'notes',
    ];

    /**
     * Identity data never goes into the audit log (it would copy personal data around).
     *
     * @var list<string>
     */
    protected array $auditExclude = ['nik', 'ktp_path'];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'phone' => null,
        'nik' => null,
        'ktp_path' => null,
        'is_active' => true,
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * "3374••••••••0001": enough to tell mechanics apart without exposing the number.
     */
    public function maskedNik(): ?string
    {
        if ($this->nik === null) {
            return null;
        }

        return substr($this->nik, 0, 4).str_repeat('•', max(strlen($this->nik) - 8, 0)).substr($this->nik, -4);
    }
}
