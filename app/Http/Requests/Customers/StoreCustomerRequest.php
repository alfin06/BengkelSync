<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;

class StoreCustomerRequest extends CustomerRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }
}
