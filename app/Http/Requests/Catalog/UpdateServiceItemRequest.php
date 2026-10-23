<?php

namespace App\Http\Requests\Catalog;

use App\Models\ServiceItem;

class UpdateServiceItemRequest extends ServiceItemRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('service_item')) ?? false;
    }

    protected function ignoredId(): ?int
    {
        $item = $this->route('service_item');

        return $item instanceof ServiceItem ? $item->id : null;
    }
}
