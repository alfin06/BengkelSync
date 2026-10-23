<?php

namespace App\Http\Requests\Catalog;

use App\Models\ServiceItem;

class StoreServiceItemRequest extends ServiceItemRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ServiceItem::class) ?? false;
    }
}
