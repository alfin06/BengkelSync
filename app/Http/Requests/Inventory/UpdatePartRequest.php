<?php

namespace App\Http\Requests\Inventory;

use App\Models\Part;

class UpdatePartRequest extends PartRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('part')) ?? false;
    }

    protected function ignoredId(): ?int
    {
        $part = $this->route('part');

        return $part instanceof Part ? $part->id : null;
    }
}
