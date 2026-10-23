<?php

namespace App\Http\Requests\Tenancy;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SwitchBranchRequest extends FormRequest
{
    private ?Branch $branch = null;

    /**
     * A user may only switch to a branch they are assigned to. Branches of other tenants
     * are not even found, because Branch is tenant-scoped.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $branch = $this->branch();

        return $user instanceof User && $branch !== null && $user->canAccessBranch($branch);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'],
        ];
    }

    public function branch(): ?Branch
    {
        return $this->branch ??= Branch::query()->find($this->integer('branch_id'));
    }
}
