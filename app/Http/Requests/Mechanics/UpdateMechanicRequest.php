<?php

namespace App\Http\Requests\Mechanics;

class UpdateMechanicRequest extends MechanicRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('mechanic')) ?? false;
    }
}
