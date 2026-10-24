<?php

namespace App\Http\Requests\Mechanics;

use App\Models\Mechanic;

class StoreMechanicRequest extends MechanicRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Mechanic::class) ?? false;
    }
}
